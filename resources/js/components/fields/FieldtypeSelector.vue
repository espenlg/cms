<template>

    <div class="h-full bg-gray-100 overflow-auto">
        <div class="bg-gray-300 px-6 py-2 border-b text-lg font-medium flex items-center justify-between">
            {{ __('Fieldtypes') }}
            <button type="button" class="btn-close" @click="close">×</button>
        </div>

        <div v-if="fieldtypesLoading" class="absolute inset-0 z-200 flex items-center justify-center text-center">
            <loading-graphic />
        </div>

        <div class="py-4 px-6 border-b bg-white flex items-center" v-if="fieldtypesLoaded">
            <input type="text" class="input-text flex-1 bg-white text-sm w-full" autofocus v-model="search" ref="search" @keydown.esc="cancelSearch" :placeholder="`${__('Search')}...`" />
        </div>

        <div class="p-4" v-if="fieldtypesLoaded">
            <div v-for="group in displayedFieldtypes" :key="group.handle" v-show="group.fieldtypes.length > 0" class="mb-8">
                <h2 v-if="group.title" v-text="group.title" class="px-2 mb-1" />
                <p v-if="group.description" v-text="group.description" class="px-2 mb-2 text-gray-700 text-sm"/>
                <div class="fieldtype-selector">
                    <div class="fieldtype-list">
                        <div class="p-2" v-for="fieldtype in group.fieldtypes" :key="fieldtype.handle">
                            <button class="bg-white border border-gray-500 flex items-center group w-full rounded hover:border-gray-600 shadow-sm hover:shadow-md pr-3"
                                @click="select(fieldtype)">
                                <div class="p-2 flex items-center border-r border-gray-500 group-hover:border-gray-600 bg-gray-200 rounded-l">
                                    <svg-icon class="h-5 w-5 text-gray-800" :name="`light/${fieldtype.icon}`" default="light/generic-field"></svg-icon>
                                </div>
                                <span class="pl-3 text-gray-800 text-md group-hover:text-gray-900">{{ fieldtype.text }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>

<script>
import Fuse from 'fuse.js';
import ProvidesFieldtypes from '../fields/ProvidesFieldtypes';

export default {

    mixins: [ProvidesFieldtypes],

    props: {
        allowTitle: {
            default: false
        },
        allowSlug: {
            default: false
        },
        allowDate: {
            default: false
        },
    },

    data: function() {
        return {
            categories: {
                text: {
                    title: __('Text & Rich Content'),
                    description: __('fieldtypes.picker.category.text.description'),
                },
                controls: {
                    title: __('Buttons & Controls'),
                    description: __('fieldtypes.picker.category.controls.description'),
                },
                media: {
                    title: __('Media'),
                    description: __('fieldtypes.picker.category.media.description'),
                },
                number: {
                    title: __('Number'),
                    description: __('fieldtypes.picker.category.number.description'),
                },
                relationship: {
                    title: __('Relationship'),
                    description: __('fieldtypes.picker.category.relationship.description'),
                },
                structured: {
                    title: __('Structured'),
                    description: __('fieldtypes.picker.category.structured.description'),
                },
                special: {
                    title: __('Special'),
                    description: __('fieldtypes.picker.category.special.description'),
                },
            },
            search: ''
        }
    },

    computed: {

        allFieldtypes() {
            if (!this.fieldtypesLoaded) return [];

            let options = this.fieldtypes.map(fieldtype => {
                return {text: fieldtype.title, value: fieldtype.handle, categories: fieldtype.categories, icon: fieldtype.icon};
            });

            if (this.allowDate) options.unshift({text: __('Publish Date'), value: 'date', categories: ['system'], isMeta: true, icon: 'date'});
            if (this.allowSlug) options.unshift({text: __('Slug'), value: 'slug', categories: ['system'], isMeta: true, icon: 'slug'});
            if (this.allowTitle) options.unshift({text: __('Title'), value: 'title', categories: ['system'], isMeta: true, icon: 'title'});

            return options;
        },

        groupedFieldtypes() {
            return _.mapObject(this.categories, (category, handle) => {
                category.handle = handle;
                category.fieldtypes = [];

                this.allFieldtypes.forEach(fieldtype => {
                    let categories = fieldtype.categories;
                    if (categories.length === 0) categories = ['special'];
                    if (categories.includes(handle)) category.fieldtypes.push(fieldtype);
                })

                return category;
            });
        },

        searchFieldtypes() {
            let options = this.allFieldtypes;

            if (this.search) {
                const fuse = new Fuse(options, {
                    findAllMatches: true,
                    threshold: 0.1,
                    minMatchCharLength: 2,
                    keys: ['text'],
                });

                options = fuse.search(this.search);
            }

            return options;
        },

        displayedFieldtypes() {
            return this.isSearching
                ? [{fieldtypes: this.searchFieldtypes}]
                : this.groupedFieldtypes;
        },

        allowMeta() {
            return this.allowTitle || this.allowSlug || this.allowDate;
        },

        isSearching() {
            return this.search;
        }
    },

    watch: {

        fieldtypesLoaded: {
            immediate: true,
            handler() {
                this.$nextTick(() => {
                    if (this.$refs.search) this.$refs.search.focus();
                });
            }
        }

    },

    methods: {

        select(selection) {
            if (selection.isMeta) {
                return this.selectMeta(selection);
            }

            const field = this.createField(selection.value);

            this.$emit('selected', field);
            this.close();
        },

        selectMeta(selection) {
            let fieldtype = selection.value;

            if (['title', 'slug'].includes(fieldtype)) {
                fieldtype = 'text';
            }

            let field = this.createField(fieldtype);

            field = Object.assign({
                display: __(`cp.${selection.value}`),
                handle: selection.value,
                type: fieldtype,
                isMeta: true
            }, field);

            this.$emit('selected', field);
            this.close();
        },

        createField(handle) {
            const fieldtype = _.findWhere(this.fieldtypes, { handle });

            // Build the initial empty field. The event listener will assign display, handle,
            // and id keys. This will be 'field_n' etc, where n would be the total root
            // level, grid, or set fields depending on the event listener location.
            let field = {
                type: fieldtype.handle,
                display: __(':title Field', {title: fieldtype.title}),
                handle: null, // The handle will be generated from the display by the "slug" fieldtype.
                icon: fieldtype.icon,
                instructions: null,
                localizable: false,
                width: 100,
                listable: 'hidden',
                isNew: true
            };

            // Vue's reactivity works best when an object already has the appropriate values.
            // We'll set up the default values for each config option. Each option might
            // have a default value defined, otherwise will just set it to null.
            let defaults = {};
            _.each(fieldtype.config, configField => {
                defaults[configField.handle] = configField.default || null;
            });

            // Smoosh the field together with the defaults.
            return Object.assign(defaults, field);
        },

        close() {
            this.search = '';
            this.filterBy = 'all';
            this.$emit('closed');
        },

        cancelSearch(event) {
            if (! this.search) return;

            event.stopPropagation();
            this.search = '';
        }

    }

}
</script>
