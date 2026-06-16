/**
 * ClientPaginator — reusable client-side pagination engine.
 *
 * Usage:
 *   const pager = new ClientPaginator({
 *       itemsSelector : '.my-item',
 *       containerId   : 'myPagination',
 *       perPage       : 20,
 *       countBadgeId  : 'myCount',       // optional
 *       scrollTargetId: 'myGrid',        // optional
 *       labelSingular : 'member',
 *       labelPlural   : 'members',
 *       filterFn      : (item) => bool,
 *   });
 *
 *   window._pagers['myPagination'] = pager;
 *   pager.refresh();
 */
class ClientPaginator {
    constructor({ itemsSelector, containerId, perPage = 20, countBadgeId = null, scrollTargetId = null, labelSingular = 'result', labelPlural = 'results', filterFn = null }) {
        this.itemsSelector  = itemsSelector;
        this.containerId    = containerId;
        this.perPage        = perPage;
        this.countBadgeId   = countBadgeId;
        this.scrollTargetId = scrollTargetId;
        this.labelSingular  = labelSingular;
        this.labelPlural    = labelPlural;
        this.filterFn       = filterFn || (() => true);
        this.currentPage    = 1;
    }

    refresh() {
        this.currentPage = 1;
        this._render();
    }

    goToPage(page) {
        this.currentPage = page;
        this._render();
        const target = this.scrollTargetId ? document.getElementById(this.scrollTargetId) : null;
        target && target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    _render() {
        const all     = Array.from(document.querySelectorAll(this.itemsSelector));
        const matched = all.filter(item => this.filterFn(item));
        const total   = matched.length;
        const totalPages = Math.max(1, Math.ceil(total / this.perPage));

        this.currentPage = Math.min(this.currentPage, totalPages);

        const start   = (this.currentPage - 1) * this.perPage;
        const pageSet = new Set(matched.slice(start, start + this.perPage));

        all.forEach(function(item) { item.style.display = pageSet.has(item) ? '' : 'none'; });

        if (this.countBadgeId) {
            const badge = document.getElementById(this.countBadgeId);
            if (badge) badge.textContent = total;
        }

        this._renderControls(total, totalPages);
    }

    _renderControls(total, totalPages) {
        const container = document.getElementById(this.containerId);
        if (!container) return;

        const label = total === 1 ? this.labelSingular : this.labelPlural;

        if (totalPages <= 1) {
            container.innerHTML = total > 0
                ? '<span class="text-sm text-muted-foreground">' + total + ' ' + label + '</span>'
                : '';
            return;
        }

        // Page window: first, last, current +/- 2, with ellipsis gaps
        const pages = [];
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= this.currentPage - 2 && i <= this.currentPage + 2)) {
                pages.push(i);
            }
        }
        const withGaps = [];
        let prev = 0;
        pages.forEach(function(p) {
            if (prev && p - prev > 1) withGaps.push('...');
            withGaps.push(p);
            prev = p;
        });

        const base     = 'inline-flex items-center justify-center w-9 h-9 text-sm rounded-lg border transition-colors';
        const inactive = base + ' font-medium text-foreground bg-white border-border hover:bg-accent hover:text-primary hover:border-primary/30 cursor-pointer';
        const active   = base + ' font-semibold text-white bg-primary border-primary cursor-default shadow-sm';
        const disabled = base + ' text-gray-400 bg-white border-border cursor-not-allowed';
        const nav      = base + ' text-foreground bg-white border-border hover:bg-accent hover:text-primary hover:border-primary/30 cursor-pointer';

        const svgPrev = '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>';
        const svgNext = '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>';

        const id = this.containerId;
        const cp = this.currentPage;

        const prevBtn = cp === 1
            ? '<span class="' + disabled + '">' + svgPrev + '</span>'
            : '<span class="' + nav + '" onclick="window._pagers[\'' + id + '\'].goToPage(' + (cp - 1) + ')">' + svgPrev + '</span>';

        const nextBtn = cp === totalPages
            ? '<span class="' + disabled + '">' + svgNext + '</span>'
            : '<span class="' + nav + '" onclick="window._pagers[\'' + id + '\'].goToPage(' + (cp + 1) + ')">' + svgNext + '</span>';

        const ellipsisSpan = '<span class="' + base + ' text-muted-foreground bg-white border-border cursor-default select-none">...</span>';

        const self = this;
        const pageButtons = withGaps.map(function(p) {
            if (p === '...') return ellipsisSpan;
            if (p === cp)    return '<span class="' + active + '">' + p + '</span>';
            return '<span class="' + inactive + '" onclick="window._pagers[\'' + id + '\'].goToPage(' + p + ')">' + p + '</span>';
        }).join('');

        const rangeStart = (cp - 1) * this.perPage + 1;
        const rangeEnd   = Math.min(cp * this.perPage, total);

        container.innerHTML =
            '<span class="text-sm text-muted-foreground">' + rangeStart + '–' + rangeEnd + ' of ' + total + ' ' + label + '</span>' +
            '<div class="inline-flex items-center gap-1">' + prevBtn + pageButtons + nextBtn + '</div>';
    }
}

window.ClientPaginator = ClientPaginator;
window._pagers = window._pagers || {};
