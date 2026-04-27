import { X } from 'lucide-react'
import { cn } from '@/lib/utils'

interface TagBadgeProps {
  tag: string
  onRemove?: () => void
  className?: string
}

export function TagBadge({ tag, onRemove, className }: TagBadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 bg-[#F5F5F5] border border-[#E0E0E0] text-[#555] text-xs px-2 py-0.5 rounded-md font-medium',
        className,
      )}
    >
      {tag}
      {onRemove && (
        <button
          type="button"
          onClick={onRemove}
          className="ml-0.5 rounded hover:opacity-70 transition-opacity"
          aria-label={`Supprimer le tag "${tag}"`}
        >
          <X size={10} strokeWidth={2.5} />
        </button>
      )}
    </span>
  )
}
