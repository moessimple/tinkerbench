export function isValidSnippetName(name: string): boolean {
    return /^[A-Za-z0-9_-]+$/.test(name);
}
