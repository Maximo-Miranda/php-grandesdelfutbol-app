<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    name: string;
    size?: number;
}>();

const size = computed(() => props.size ?? 56);

// Deterministic hash from club name
function hashString(str: string): number {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = ((hash << 5) - hash + str.charCodeAt(i)) | 0;
    }
    return Math.abs(hash);
}

function seededRandom(seed: number, index: number): number {
    const x = Math.sin(seed + index) * 10000;
    return x - Math.floor(x);
}

// Generate unique colors and pattern from club name
const seed = computed(() => hashString(props.name));

const primaryHue = computed(() => seed.value % 360);
const secondaryHue = computed(() => (primaryHue.value + 120 + (seed.value % 60)) % 360);

const primary = computed(() => `hsl(${primaryHue.value}, 70%, 45%)`);
const primaryLight = computed(() => `hsl(${primaryHue.value}, 70%, 55%)`);
const secondary = computed(() => `hsl(${secondaryHue.value}, 60%, 50%)`);
const dark = computed(() => `hsl(${primaryHue.value}, 50%, 20%)`);

const pattern = computed(() => Math.floor(seededRandom(seed.value, 1) * 7));
const initials = computed(() => {
    const words = props.name.trim().split(/\s+/).filter(w => w.length > 1 && !['de', 'del', 'la', 'el', 'los', 'las', 'fc', 'cf'].includes(w.toLowerCase()));
    if (words.length >= 2) return (words[0][0] + words[1][0]).toUpperCase();
    return props.name.trim().substring(0, 2).toUpperCase();
});

const svgId = computed(() => `shield-${seed.value}`);
</script>

<template>
    <svg
        :width="size"
        :height="size"
        :viewBox="'0 0 100 120'"
        xmlns="http://www.w3.org/2000/svg"
        class="shrink-0 drop-shadow-md"
    >
        <defs>
            <!-- Shield clip path -->
            <clipPath :id="`${svgId}-clip`">
                <path d="M50 2 L95 20 L95 65 Q95 95 50 118 Q5 95 5 65 L5 20 Z" />
            </clipPath>

            <!-- Gradient -->
            <linearGradient :id="`${svgId}-grad`" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" :stop-color="primaryLight" />
                <stop offset="100%" :stop-color="primary" />
            </linearGradient>
        </defs>

        <!-- Shield background -->
        <g :clip-path="`url(#${svgId}-clip)`">
            <rect x="0" y="0" width="100" height="120" :fill="`url(#${svgId}-grad)`" />

            <!-- Pattern 0: Horizontal stripes -->
            <g v-if="pattern === 0">
                <rect x="0" y="30" width="100" height="15" :fill="secondary" opacity="0.6" />
                <rect x="0" y="60" width="100" height="15" :fill="secondary" opacity="0.6" />
                <rect x="0" y="90" width="100" height="15" :fill="secondary" opacity="0.4" />
            </g>

            <!-- Pattern 1: Vertical stripes -->
            <g v-else-if="pattern === 1">
                <rect x="0" y="0" width="20" height="120" :fill="secondary" opacity="0.5" />
                <rect x="40" y="0" width="20" height="120" :fill="secondary" opacity="0.5" />
                <rect x="80" y="0" width="20" height="120" :fill="secondary" opacity="0.5" />
            </g>

            <!-- Pattern 2: Diagonal sash -->
            <g v-else-if="pattern === 2">
                <polygon points="30,0 55,0 100,80 100,120 70,120 0,35 0,0" :fill="secondary" opacity="0.5" />
            </g>

            <!-- Pattern 3: Chevron -->
            <g v-else-if="pattern === 3">
                <polygon points="50,45 95,20 95,40 50,65 5,40 5,20" :fill="secondary" opacity="0.6" />
            </g>

            <!-- Pattern 4: Center circle -->
            <g v-else-if="pattern === 4">
                <circle cx="50" cy="55" r="28" :fill="secondary" opacity="0.5" />
                <circle cx="50" cy="55" r="18" :fill="primary" opacity="0.8" />
            </g>

            <!-- Pattern 5: Half and half -->
            <g v-else-if="pattern === 5">
                <rect x="50" y="0" width="50" height="120" :fill="secondary" opacity="0.5" />
            </g>

            <!-- Pattern 6: Diamond -->
            <g v-else>
                <polygon points="50,20 80,55 50,90 20,55" :fill="secondary" opacity="0.5" />
            </g>

            <!-- Top accent bar -->
            <rect x="0" y="0" width="100" height="5" :fill="dark" opacity="0.4" />
        </g>

        <!-- Shield border -->
        <path
            d="M50 2 L95 20 L95 65 Q95 95 50 118 Q5 95 5 65 L5 20 Z"
            fill="none"
            :stroke="dark"
            stroke-width="3"
            stroke-linejoin="round"
        />

        <!-- Inner border accent -->
        <path
            d="M50 7 L90 23 L90 64 Q90 91 50 113 Q10 91 10 64 L10 23 Z"
            fill="none"
            stroke="rgba(255,255,255,0.25)"
            stroke-width="1"
        />

        <!-- Initials -->
        <text
            x="50"
            y="68"
            text-anchor="middle"
            fill="white"
            font-size="28"
            font-weight="800"
            font-family="system-ui, sans-serif"
            style="text-shadow: 0 2px 4px rgba(0,0,0,0.4)"
        >
            {{ initials }}
        </text>
    </svg>
</template>
