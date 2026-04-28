import { ChevronLeft, ChevronRight } from 'lucide-react'
import { cn } from '@/lib/utils'

interface PaginationProps {
  currentPage: number
  totalPages: number
  onPageChange: (page: number) => void
  className?: string
}

export function Pagination({ currentPage, totalPages, onPageChange, className }: PaginationProps) {
  if (totalPages <= 1) return null

  const pages: (number | '...')[] = []
  if (totalPages <= 7) {
    for (let i = 1; i <= totalPages; i++) pages.push(i)
  } else {
    pages.push(1)
    if (currentPage > 3) pages.push('...')
    for (let i = Math.max(2, currentPage - 1); i <= Math.min(totalPages - 1, currentPage + 1); i++) {
      pages.push(i)
    }
    if (currentPage < totalPages - 2) pages.push('...')
    pages.push(totalPages)
  }

  return (
    <div className={cn('flex items-center justify-center gap-1', className)}>
      <button
        onClick={() => onPageChange(currentPage - 1)}
        disabled={currentPage === 1}
        className="p-1.5 rounded-lg text-secondary hover:bg-muted border border-strong disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
      >
        <ChevronLeft size={16} />
      </button>

      {pages.map((page, i) =>
        page === '...' ? (
          <span key={`dots-${i}`} className="px-2 py-1 text-muted text-sm">…</span>
        ) : (
          <button
            key={page}
            onClick={() => onPageChange(page)}
            className={cn(
              'min-w-[32px] h-8 px-2 rounded-lg text-sm font-medium transition-colors border',
              page === currentPage
                ? 'bg-[#1A1A1A] text-white border-[#1A1A1A]'
                : 'text-secondary hover:bg-muted border-strong',
            )}
          >
            {page}
          </button>
        ),
      )}

      <button
        onClick={() => onPageChange(currentPage + 1)}
        disabled={currentPage === totalPages}
        className="p-1.5 rounded-lg text-secondary hover:bg-muted border border-strong disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
      >
        <ChevronRight size={16} />
      </button>
    </div>
  )
}
