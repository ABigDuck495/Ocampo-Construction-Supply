import Fuse from 'fuse.js';

const SEARCH_OPTIONS = {
    threshold: 0.38,
    ignoreLocation: true,
    minMatchCharLength: 2,
};

export function fuzzySearch(items, query, keys) {
    const normalizedQuery = query.trim();
    if (!normalizedQuery) return items;

    return new Fuse(items, { ...SEARCH_OPTIONS, keys })
        .search(normalizedQuery)
        .map(result => result.item);
}