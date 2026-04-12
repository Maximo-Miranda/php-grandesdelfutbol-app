import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

export type NewsUnreadCount = { count: number; hasBreaking: boolean };

type UseNewsBadgeReturn = {
    newsUnread: ComputedRef<NewsUnreadCount>;
    badgeLabel: ComputedRef<string>;
    showBadge: ComputedRef<boolean>;
};

export function useNewsBadge(): UseNewsBadgeReturn {
    const page = usePage();
    const { isCurrentOrParentUrl } = useCurrentUrl();

    const newsUnread = computed<NewsUnreadCount>(
        () => page.props.newsUnreadCount ?? { count: 0, hasBreaking: false },
    );

    const badgeLabel = computed(() =>
        newsUnread.value.count > 9 ? '9+' : String(newsUnread.value.count),
    );

    const showBadge = computed(
        () => newsUnread.value.count > 0 && !isCurrentOrParentUrl('/news'),
    );

    return { newsUnread, badgeLabel, showBadge };
}
