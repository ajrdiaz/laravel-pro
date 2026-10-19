export type Section<T> = {
    name: string;
    items: T[];
};

/**
 * Group consecutive items that share a `section`, keeping their order, so a long
 * list can be shown under headings such as "Fundamentos" and "Frontend".
 */
export function groupBySection<T extends { section: string }>(
    items: T[],
): Section<T>[] {
    const sections: Section<T>[] = [];

    for (const item of items) {
        const last = sections.at(-1);

        if (last && last.name === item.section) {
            last.items.push(item);
        } else {
            sections.push({ name: item.section, items: [item] });
        }
    }

    return sections;
}
