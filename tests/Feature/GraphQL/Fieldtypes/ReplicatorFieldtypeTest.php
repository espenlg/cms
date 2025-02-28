<?php

namespace Tests\Feature\GraphQL\Fieldtypes;

use Facades\Statamic\Fields\BlueprintRepository;
use Facades\Tests\Factories\EntryFactory;
use Statamic\Facades\Blueprint;
use Tests\Feature\GraphQL\EnablesQueries;
use Tests\PreventSavingStacheItemsToDisk;
use Tests\TestCase;

/** @group graphql */
class ReplicatorFieldtypeTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;
    use EnablesQueries;

    protected $enabledQueries = ['collections'];

    public function setUp(): void
    {
        parent::setUp();
        BlueprintRepository::partialMock();
    }

    /**
     * @test
     *
     * @dataProvider groupedSetsProvider
     */
    public function it_outputs_replicator_fields($isGrouped)
    {
        $article = Blueprint::makeFromFields([
            'things' => [
                'type' => 'replicator',
                'sets' => $this->groupSets($isGrouped, [
                    'meal' => [
                        'fields' => [
                            ['handle' => 'food', 'field' => ['type' => 'text']],
                            ['handle' => 'drink', 'field' => ['type' => 'markdown']], // using markdown to show nested fields are resolved using their fieldtype.
                        ],
                    ],
                    'car' => [
                        'fields' => [
                            ['handle' => 'make', 'field' => ['type' => 'text']],
                            ['handle' => 'model', 'field' => ['type' => 'text']],
                            ['handle' => 'trims', 'field' => ['type' => 'entries']], // using entries to query builders get resolved
                        ],
                    ],
                ]),
            ],
        ]);

        $trim = Blueprint::makeFromFields([]);

        BlueprintRepository::shouldReceive('in')->with('collections/blog')->andReturn(collect([
            'article' => $article->setHandle('article'),
        ]));

        BlueprintRepository::shouldReceive('in')->with('collections/trims')->andReturn(collect([
            'trim' => $trim->setHandle('trim'),
        ]));

        EntryFactory::collection('blog')->id('1')->data([
            'title' => 'Main Post',
            'things' => [
                ['id' => '1', 'type' => 'meal', 'food' => 'burger', 'drink' => 'coke _zero_'],
                ['id' => '2', 'type' => 'car', 'make' => 'toyota', 'model' => 'corolla', 'trims' => ['trim1']],
                ['type' => 'meal', 'food' => 'salad', 'drink' => 'water'], // id intentionally omitted
            ],
        ])->create();

        EntryFactory::collection('trims')->id('trim1')->data(['title' => 'Trim One'])->create();

        $query = <<<'GQL'
{
    entry(id: "1") {
        title
        ... on Entry_Blog_Article {
            things {
                ... on Set_Things_Meal {
                    id
                    type
                    food
                    drink
                    drink_md: drink(format: "markdown")
                }
                ... on Set_Things_Car {
                    id
                    type
                    make
                    model
                    trims {
                        title
                    }
                }
            }
        }
    }
}
GQL;

        $this
            ->withoutExceptionHandling()
            ->post('/graphql', ['query' => $query])
            ->assertGqlOk()
            ->assertExactJson(['data' => [
                'entry' => [
                    'title' => 'Main Post',
                    'things' => [
                        ['id' => '1', 'type' => 'meal', 'food' => 'burger', 'drink' => "<p>coke <em>zero</em></p>\n", 'drink_md' => 'coke _zero_'],
                        ['id' => '2', 'type' => 'car', 'make' => 'toyota', 'model' => 'corolla', 'trims' => [['title' => 'Trim One']]],
                        ['id' => null, 'type' => 'meal', 'food' => 'salad', 'drink' => "<p>water</p>\n", 'drink_md' => 'water'],
                    ],
                ],
            ]]);
    }

    /** @test */
    public function it_outputs_nested_replicator_fields()
    {
        $article = Blueprint::makeFromFields([
            'things' => [
                'type' => 'replicator',
                'sets' => [
                    'meal' => [
                        'fields' => [
                            ['handle' => 'food', 'field' => ['type' => 'text']],
                            ['handle' => 'drink', 'field' => ['type' => 'text']],
                            [
                                'handle' => 'extras',
                                'field' => [
                                    'type' => 'replicator',
                                    'sets' => [
                                        'food' => [
                                            'fields' => [
                                                ['handle' => 'item', 'field' => ['type' => 'text']],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'car' => [
                        'fields' => [
                            ['handle' => 'make', 'field' => ['type' => 'text']],
                            ['handle' => 'model', 'field' => ['type' => 'text']],
                        ],
                    ],
                ],
            ],
        ]);

        BlueprintRepository::shouldReceive('in')->with('collections/blog')->andReturn(collect([
            'article' => $article->setHandle('article'),
        ]));

        EntryFactory::collection('blog')->id('1')->data([
            'title' => 'Main Post',
            'things' => [
                ['type' => 'meal', 'food' => 'burger', 'drink' => 'coke', 'extras' => [
                    ['type' => 'food', 'item' => 'fries'],
                    ['type' => 'food', 'item' => 'ketchup'],
                ]],
                ['type' => 'car', 'make' => 'toyota', 'model' => 'corolla'],
                ['type' => 'meal', 'food' => 'salad', 'drink' => 'water', 'extras' => [
                    ['type' => 'food', 'item' => 'dressing'],
                ]],
            ],
        ])->create();

        $query = <<<'GQL'
{
    entry(id: "1") {
        title
        ... on Entry_Blog_Article {
            things {
                ... on Set_Things_Meal {
                    type
                    food
                    drink
                    extras {
                        ... on Set_Things_Extras_Food {
                            type
                            item
                        }
                    }
                }
                ... on Set_Things_Car {
                    type
                    make
                    model
                }
            }
        }
    }
}
GQL;

        $this
            ->withoutExceptionHandling()
            ->post('/graphql', ['query' => $query])
            ->assertGqlOk()
            ->assertExactJson(['data' => [
                'entry' => [
                    'title' => 'Main Post',
                    'things' => [
                        ['type' => 'meal', 'food' => 'burger', 'drink' => 'coke', 'extras' => [
                            ['type' => 'food', 'item' => 'fries'],
                            ['type' => 'food', 'item' => 'ketchup'],
                        ]],
                        ['type' => 'car', 'make' => 'toyota', 'model' => 'corolla'],
                        ['type' => 'meal', 'food' => 'salad', 'drink' => 'water', 'extras' => [
                            ['type' => 'food', 'item' => 'dressing'],
                        ]],
                    ],
                ],
            ]]);
    }

    /**
     * @test
     *
     * @see https://github.com/statamic/cms/issues/3200
     **/
    public function it_outputs_replicator_fields_with_value_based_subfields()
    {
        // Using an `entries` field set to max_items 1, which would augment
        // to a Value object. This test is checking that the Value object
        // is converted appropriately to an Entry. A similar thing would
        // happen for `assets` fields converting to Asset objects, etc.

        $article = Blueprint::makeFromFields([
            'things' => [
                'type' => 'replicator',
                'sets' => [
                    'relation' => [
                        'fields' => [
                            [
                                'handle' => 'entry',
                                'field' => ['type' => 'entries', 'max_items' => 1],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        BlueprintRepository::shouldReceive('in')->with('collections/blog')->andReturn(collect([
            'article' => $article->setHandle('article'),
        ]));

        EntryFactory::collection('blog')->id('1')->data([
            'title' => 'Main Post',
            'things' => [
                [
                    'type' => 'relation',
                    'entry' => '2',
                ],
            ],
        ])->create();

        EntryFactory::collection('blog')->id('2')->data(['title' => 'Other Post'])->create();

        $query = <<<'GQL'
{
    entry(id: "1") {
        title
        ... on Entry_Blog_Article {
            things {
                ... on Set_Things_Relation {
                    type
                    entry {
                        title
                    }
                }
            }
        }
    }
}
GQL;

        $this
            ->withoutExceptionHandling()
            ->post('/graphql', ['query' => $query])
            ->assertGqlOk()
            ->assertExactJson(['data' => [
                'entry' => [
                    'title' => 'Main Post',
                    'things' => [
                        [
                            'type' => 'relation',
                            'entry' => [
                                'title' => 'Other Post',
                            ],
                        ],
                    ],
                ],
            ]]);
    }

    public function groupedSetsProvider()
    {
        return [
            'grouped sets (new)' => [true],
            'ungrouped sets (old)' => [false],
        ];
    }

    private function groupSets($shouldGroup, $sets)
    {
        if (! $shouldGroup) {
            return $sets;
        }

        return [
            'group_one' => ['sets' => $sets],
        ];
    }
}
