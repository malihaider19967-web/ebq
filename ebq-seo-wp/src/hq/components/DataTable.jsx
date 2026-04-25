import { __ } from '@wordpress/i18n';
import { Spinner, EmptyState } from './primitives';

/**
 * Sortable, paginated data table. Columns describe themselves as
 *   { key, label, render?, sortable?, align? }
 * Sort and page state lives in the parent so the URL/query is the source of
 * truth — this component is intentionally state-light.
 */
export function DataTable({
	columns,
	rows,
	loading,
	meta,
	sort,
	dir,
	onSort,
	onPage,
	emptyTitle = __('Nothing here yet', 'ebq-seo'),
	emptySub,
}) {
	const total = meta?.total ?? rows?.length ?? 0;
	const page = meta?.page ?? 1;
	const lastPage = meta?.last_page ?? 1;
	const perPage = meta?.per_page ?? 25;

	return (
		<div className="ebq-hq-table-wrap">
			<table className="ebq-hq-table">
				<thead>
					<tr>
						{columns.map((c) => {
							const isSorted = sort === c.key;
							const arrow = isSorted ? (dir === 'desc' ? '▼' : '▲') : '';
							return (
								<th
									key={c.key}
									className={`ebq-hq-table__th${c.align === 'right' ? ' ebq-hq-table__th--right' : ''}${c.sortable ? ' is-sortable' : ''}${isSorted ? ' is-sorted' : ''}`}
									onClick={c.sortable && onSort ? () => onSort(c.key) : undefined}
								>
									<span>
										{c.label}
										{arrow ? <span className="ebq-hq-table__arrow">{arrow}</span> : null}
									</span>
								</th>
							);
						})}
					</tr>
				</thead>
				<tbody>
					{loading ? (
						<tr><td colSpan={columns.length}><div className="ebq-hq-table__loading"><Spinner /> {__('Loading…', 'ebq-seo')}</div></td></tr>
					) : (rows || []).length === 0 ? (
						<tr><td colSpan={columns.length}><EmptyState title={emptyTitle} sub={emptySub} /></td></tr>
					) : (
						(rows || []).map((row, idx) => (
							<tr key={row.id ?? row.page ?? row.keyword ?? idx} className="ebq-hq-table__tr">
								{columns.map((c) => (
									<td
										key={c.key}
										className={`ebq-hq-table__td${c.align === 'right' ? ' ebq-hq-table__td--right' : ''}`}
									>
										{c.render ? c.render(row) : row[c.key]}
									</td>
								))}
							</tr>
						))
					)}
				</tbody>
			</table>
			{total > perPage && onPage ? (
				<footer className="ebq-hq-table__foot">
					<span>{__('Page', 'ebq-seo')} {page} / {lastPage} · {total.toLocaleString()} {__('rows', 'ebq-seo')}</span>
					<div>
						<button type="button" className="ebq-hq-btn ebq-hq-btn--ghost ebq-hq-btn--sm" disabled={page <= 1} onClick={() => onPage(page - 1)}>← {__('Prev', 'ebq-seo')}</button>
						<button type="button" className="ebq-hq-btn ebq-hq-btn--ghost ebq-hq-btn--sm" disabled={page >= lastPage} onClick={() => onPage(page + 1)}>{__('Next', 'ebq-seo')} →</button>
					</div>
				</footer>
			) : null}
		</div>
	);
}
