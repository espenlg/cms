<template>

    <th v-show="field.type !== 'hidden'">
        <div class="flex items-center justify-between">
            <div><template v-if="!field.hide_display">{{ field.display || field.handle }}</template></div>
            <svg-icon name="micro/circle-help" class="text-gray-600 hover:text-gray-700 h-3 w-3 text-xs" v-if="field.instructions" v-tooltip="$options.filters.markdown(field.instructions)" />
        </div>
    </th>

</template>

<script>
export default {

    props: {
        field: {
            type: Object,
            required: true
        }
    }

}
</script>
