<template>
    <popover class="dropdown-list" :disabled="disabled" :placement="placement" :autoclose="autoclose">
        <template #trigger>
            <slot name="trigger">
                <button class="rotating-dots-button" :aria-label="__('Open Dropdown')">
                    <svg class="rotating-dots fill-current" width="12" viewBox="0 0 24 24"><circle cx="3" cy="12" r="3"/><circle cx="12" cy="12" r="3"/><circle cx="21" cy="12" r="3"/></svg>
                </button>
            </slot>
        </template>
        <template #default><slot /></template>
    </popover>
</template>

<script>
export default {
    props: {
        disabled: {
            type: Boolean,
            default: false
        },
        placement: {
            type: String,
            default: 'bottom-end'
        },
        autoclose: {
            type: Boolean,
            default: false
        }
    },
    computed: {
        strategy() {
            return this.scroll ? 'fixed' : 'absolute';
        }
    }

}
</script>
