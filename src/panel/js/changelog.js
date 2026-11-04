/**
 * Changelog parser & renderer (Updates page)
 *
 * Reads the markdown written by auto-changelog (see src/auto-changelog.hbs) line by line, instead of converting it
 * to HTML and patching tags with regex, so releases, change types and commits can be rendered as structured UI.
 * Used for both the local CHANGELOG.md and the release notes served by the update server (same format).
 */

/**
 * Change types in display order. "match" is tested against the group headings auto-changelog writes,
 * e.g. "#### Chores And Housekeeping". Unknown headings ("General Changes") fall back to "other".
 */
const CHANGELOG_TYPES = [
    {key: 'breaking', label: 'Breaking changes', icon: 'fa-triangle-exclamation', match: /^breaking/i},
    {key: 'feat', label: 'New features', icon: 'fa-star', match: /^new features|^feat/i},
    {key: 'fix', label: 'Fixes', icon: 'fa-bug', match: /^fix/i},
    {key: 'perf', label: 'Performance', icon: 'fa-bolt', match: /^perf/i},
    {key: 'other', label: 'Other changes', icon: 'fa-layer-group', match: /^general|^other/i},
    {key: 'refactor', label: 'Refactoring', icon: 'fa-code', match: /^refactor/i},
    {key: 'style', label: 'Code style', icon: 'fa-paintbrush', match: /^tidying|^style/i},
    {key: 'docs', label: 'Documentation', icon: 'fa-book', match: /^doc/i},
    {key: 'test', label: 'Tests', icon: 'fa-flask', match: /test/i},
    {key: 'chore', label: 'Chores', icon: 'fa-broom', match: /^chore/i},
];

// "### [v1.1.1](compare-url) - 14 April 2026", "### v1.0.1", or "## [v2.0.0](...)" for major releases
const CHANGELOG_RELEASE = /^#{2,3}\s+(?:\[(v?\d[^\]]*)\]\(([^)\s]+)\)|(v?\d+\.\d+\S*))\s*(?:-\s*(.*))?$/;

// Trailing commit link: [`f57d4ac`](https://github.com/owner/repo/commit/f57d4ace...)
const CHANGELOG_COMMIT = /\s*\[`([0-9a-f]{7,40})`\]\(([^)\s]+)\)\s*$/i;

// Conventional commit prefix, redundant once the item sits under its type heading
const CHANGELOG_PREFIX = /^(?:feat|fix|fixed|chore|docs|refactor|style|perf|test)(?:\([^)]*\))?(!?):\s*/i;

// Any other short prefix ("UX: ...") carries meaning of its own, it is shown as a tag instead
const CHANGELOG_TAG = /^([a-z]{2,12}):\s+/i;


/**
 * Parse auto-changelog markdown into releases:
 * [{ version, url, date, summary: [], groups: [{ type, items: [{ text, tag, hash, commitUrl, breaking }] }] }]
 */
function parseChangelog(md) {

    const releases = [];
    let release = null;
    let group = null;

    // Release notes may start straight with a group or item, without a release heading
    function currentRelease() {
        if (!release) {
            release = {version: null, url: null, date: null, summary: [], groups: []};
            releases.push(release);
        }

        return release;
    }

    function groupFor(type) {
        const groups = currentRelease().groups;
        let found = groups.find(g => g.type === type);
        if (!found) {
            found = {type, items: []};
            groups.push(found);
        }

        return found;
    }

    for (const line of (md || '').replace(/\r/g, '').split('\n')) {

        const releaseMatch = line.match(CHANGELOG_RELEASE);
        if (releaseMatch) {
            release = {
                version: releaseMatch[1] || releaseMatch[3],
                url: releaseMatch[2] || null,
                date: (releaseMatch[4] || '').trim() || null,
                summary: [],
                groups: [],
            };
            releases.push(release);
            group = null;
            continue;
        }

        const groupMatch = line.match(/^####\s+(.+)$/);
        if (groupMatch) {
            const heading = groupMatch[1].trim();
            group = groupFor(CHANGELOG_TYPES.find(t => t.match.test(heading)) || CHANGELOG_TYPES.find(t => t.key === 'other'));
            continue;
        }

        const itemMatch = line.match(/^\s*[-*]\s+(.+)$/);
        if (itemMatch) {
            // Merges and fixes are listed before any group heading
            (group || groupFor(CHANGELOG_TYPES.find(t => t.key === 'other'))).items.push(parseChangelogItem(itemMatch[1]));
            continue;
        }

        if (line.trim() && !line.startsWith('#') && release) {
            release.summary.push(line.trim());
        }

    }

    // auto-changelog writes headings for empty groups too
    for (const r of releases) {
        r.groups = r.groups
            .filter(g => g.items.length)
            .sort((a, b) => CHANGELOG_TYPES.indexOf(a.type) - CHANGELOG_TYPES.indexOf(b.type));
    }

    return releases;

}


/**
 * Split one changelog line into its message, tag, commit hash and breaking flag
 */
function parseChangelogItem(text) {

    const item = {text: '', tag: null, hash: null, commitUrl: null, breaking: false};

    const commit = text.match(CHANGELOG_COMMIT);
    if (commit) {
        item.hash = commit[1];
        item.commitUrl = commit[2];
        text = text.slice(0, commit.index);
    }

    text = text.replace(/\*\*breaking change:\*\*\s*|\bbreaking(?: change)?:\s*/gi, () => {
        item.breaking = true;
        return '';
    });

    text = text.trim().replace(CHANGELOG_PREFIX, (prefix, bang) => {
        if (bang) item.breaking = true;
        return '';
    });

    text = text.replace(CHANGELOG_TAG, (prefix, tag) => {
        item.tag = tag;
        return '';
    });

    item.text = text.charAt(0).toUpperCase() + text.slice(1);
    return item;

}


/**
 * Escape markup, but keep "&" so entities already escaped by auto-changelog (e.g. &lt;?php) still render
 */
function escapeChangelog(text) {
    return text.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}


/**
 * Inline formatting for a change message: `code`, **bold**, [links](https://...) and #123 issue references
 */
function formatChangelogText(text, repo) {

    // Odd indexes are `code` spans, nothing inside them gets formatted
    return text.split(/(`[^`]+`)/).map((part, i) => {

        if (i % 2) {
            return '<code>' + escapeChangelog(part.slice(1, -1)) + '</code>';
        }

        let html = escapeChangelog(part)
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');

        if (repo) {
            html = html.replace(/(^|[\s(])#(\d+)\b/g, `$1<a href="${repo}/issues/$2" target="_blank" rel="noopener">#$2</a>`);
        }

        return html;

    }).join('');

}


/**
 * GitHub repository URL from a commit or compare link, used to link issue references
 */
function changelogRepo(url) {
    const match = (url || '').match(/^https:\/\/github\.com\/[^/]+\/[^/]+/);
    return match ? match[0] : null;
}


/**
 * Change groups of one release (also used alone for the "update available" notes)
 */
function renderChangelogBody(release) {

    if (!release.groups.length) {
        return '<p class="changelog-empty">No changes are listed for this release.</p>';
    }

    const summary = release.summary.length
        ? `<p class="changelog-intro">${formatChangelogText(release.summary.join(' '), changelogRepo(release.url))}</p>`
        : '';

    const groups = release.groups.map(group => {

        const items = group.items.map(item => {

            const repo = changelogRepo(item.commitUrl || release.url);
            const tags = [
                item.breaking && group.type.key !== 'breaking' ? '<span class="changelog-tag is-breaking">Breaking</span>' : '',
                item.tag ? `<span class="changelog-tag">${escapeChangelog(item.tag)}</span>` : '',
            ].join('');
            const hash = item.hash
                ? `<a class="changelog-hash" href="${escapeChangelog(item.commitUrl)}" target="_blank" rel="noopener" title="View commit">${item.hash.slice(0, 7)}</a>`
                : '';

            return `<li class="changelog-item"><span class="changelog-text">${tags}${formatChangelogText(item.text, repo)}</span>${hash}</li>`;

        }).join('');

        return `
            <section class="changelog-group" data-type="${group.type.key}">
                <h4 class="changelog-group-title">
                    <span class="changelog-icon"><i class="fas ${group.type.icon}" aria-hidden="true"></i></span>
                    ${group.type.label}
                    <span class="changelog-group-count">${group.items.length}</span>
                </h4>
                <ul class="changelog-items">${items}</ul>
            </section>`;

    }).join('');

    return summary + groups;

}


/**
 * Full release history: one collapsible row per release, newest open.
 * The local CHANGELOG.md ships with the installed release, so its newest entry isn't always the latest available.
 */
function renderChangelog(releases, installedVersion) {

    const installed = String(installedVersion || '').trim().replace(/^v/i, '');

    return releases.map((release, index) => {

        const version = release.version || 'Unreleased';
        const badge = version.replace(/^v/i, '') === installed
            ? '<span class="changelog-badge"><i class="fas fa-check" aria-hidden="true"></i> Installed</span>'
            : '';

        // At-a-glance mix of change types, so a release can be judged without opening it
        const chips = release.groups.map(group => `
            <span class="changelog-chip" data-type="${group.type.key}" title="${group.type.label}: ${group.items.length}">
                <i class="fas ${group.type.icon}" aria-hidden="true"></i>${group.items.length}<span class="sr-only"> ${group.type.label}</span>
            </span>`).join('');

        const compare = release.url
            ? `<a class="changelog-compare" href="${escapeChangelog(release.url)}" target="_blank" rel="noopener"><i class="fas fa-code-compare" aria-hidden="true"></i> View code changes</a>`
            : '';

        return `
            <details class="changelog-release"${index === 0 ? ' open' : ''}>
                <summary class="changelog-summary">
                    <span class="changelog-version"><span class="changelog-version-number">${escapeChangelog(version)}</span>${badge}</span>
                    <span class="changelog-mix">${chips}</span>
                    <span class="changelog-date">${escapeChangelog(release.date || '')}</span>
                    <i class="fas fa-chevron-down changelog-chevron" aria-hidden="true"></i>
                </summary>
                <div class="changelog-body">${renderChangelogBody(release)}${compare}</div>
            </details>`;

    }).join('');

}
