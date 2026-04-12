import { ref } from 'vue';

/**
 * Shared state flag used to coordinate between the article detail page
 * (where mutations happen: posting comments, likes, etc.) and the feed
 * (where the mutated card's counters need to refresh when the user
 * navigates back).
 *
 * The marker is just a boolean — Feed.vue consumes it on mount / navigate
 * and triggers a partial reload of the `articles` prop. Because the prop
 * is declared as `Inertia::scroll(...)->matchOn('data.ulid')` on the backend,
 * Inertia upserts individual cards by ulid without touching scroll position
 * or the accumulated infinite-scroll pages.
 */
const isDirty = ref(false);

export function useNewsFeedDirty() {
    return {
        markDirty: (): void => {
            isDirty.value = true;
        },
        consumeDirty: (): boolean => {
            if (!isDirty.value) {
                return false;
            }

            isDirty.value = false;

            return true;
        },
    };
}
