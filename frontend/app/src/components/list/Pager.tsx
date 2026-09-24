import { Button } from '@salvon/components/button';

type Summary = {
  page: number;
  pages: number;
  per_page: number;
  total: number;
};

type Props = {
  summary: Summary;
  loading?: boolean;
  /** Dozwolone wartości — ta sama lista co na serwerze. */
  sizes?: number[];
  onPage: (page: number) => void;
  onPerPage: (perPage: number) => void;
  translate: (key: string, options?: Record<string, unknown>) => string;
};

/**
 * Stronicowanie pod listą: ile wierszy na stronę, gdzie jestem, dokąd
 * dalej. Jeden komponent dla listy zleceń i kolejki produkcji — dwa
 * pagery napisane osobno rozjechałyby się przy pierwszej poprawce.
 *
 * Liczba wierszy jest zapamiętywana przy koncie przez ekran, który
 * pager wywołuje; sam pager niczego nie zapisuje.
 */
export default function Pager({
  summary,
  loading = false,
  sizes = [50, 100, 200],
  onPage,
  onPerPage,
  translate: t,
}: Props) {
  if (summary.total === 0) {
    return null;
  }

  return (
    <nav className="ge-pager" aria-label={t('page.pager.label')}>
      <div className="ge-pager__size">
        <span>{t('page.pager.per_page')}</span>
        {sizes.map((size) => (
          <button
            key={size}
            type="button"
            className={
              size === summary.per_page
                ? 'ge-pager__option is-active'
                : 'ge-pager__option'
            }
            aria-pressed={size === summary.per_page}
            onClick={() => onPerPage(size)}
          >
            {size}
          </button>
        ))}
      </div>
      <Button
        variant="text"
        disabled={loading || summary.page <= 1}
        onClick={() => onPage(summary.page - 1)}
      >
        {t('page.pager.prev')}
      </Button>
      <span className="ge-pager__where">
        {t('page.pager.page_of', {
          page: summary.page,
          pages: summary.pages,
        })}
      </span>
      <Button
        variant="text"
        disabled={loading || summary.page >= summary.pages}
        onClick={() => onPage(summary.page + 1)}
      >
        {t('page.pager.next')}
      </Button>
    </nav>
  );
}
