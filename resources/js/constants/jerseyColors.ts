export type JerseyColor = {
    hex: string;
    label: string;
    light?: boolean;
};

export const JERSEY_COLORS: JerseyColor[] = [
    { hex: '#ffffff', label: 'Blanco', light: true },
    { hex: '#1a1a1a', label: 'Negro' },
    { hex: '#6b7280', label: 'Gris' },

    { hex: '#dc2626', label: 'Rojo' },
    { hex: '#991b1b', label: 'Guinda' },
    { hex: '#ea580c', label: 'Naranja' },

    { hex: '#facc15', label: 'Amarillo', light: true },
    { hex: '#16a34a', label: 'Verde' },
    { hex: '#065f46', label: 'Verde oscuro' },

    { hex: '#06b6d4', label: 'Celeste', light: true },
    { hex: '#2563eb', label: 'Azul' },
    { hex: '#1e3a5f', label: 'Azul marino' },

    { hex: '#7c3aed', label: 'Morado' },
    { hex: '#db2777', label: 'Rosa' },
    { hex: '#92400e', label: 'Cafe' },
];

export function colorLabel(hex: string): string {
    return JERSEY_COLORS.find((c) => c.hex === hex)?.label ?? hex;
}
