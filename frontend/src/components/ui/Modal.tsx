import { useEffect, type ReactNode } from 'react'
import { X } from 'lucide-react'
import { cn } from '@/lib/utils'

interface ModalProps {
  open: boolean
  onClose: () => void
  title: string
  children: ReactNode
  className?: string
}

export function Modal({ open, onClose, title, children, className }: ModalProps) {
  useEffect(() => {
    const handler = (e: KeyboardEvent) => e.key === 'Escape' && onClose()
    document.addEventListener('keydown', handler)
    return () => document.removeEventListener('keydown', handler)
  }, [onClose])

  if (!open) return null

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" onClick={onClose} />
      <div className={cn('relative z-10 bg-white rounded-lg shadow-2xl w-full max-w-lg mx-4 border border-[#E0E0E0]', className)}>
        <div className="flex items-center justify-between px-6 py-4 border-b border-[#E8E8E8] bg-[#FAFAFA] rounded-t-lg">
          <div className="flex items-center gap-3">
            <div className="w-1 h-5 bg-[#F5A800] rounded-full" />
            <h2 className="text-base font-bold text-[#1A1A1A] tracking-tight">{title}</h2>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-md text-[#888] hover:text-[#333] hover:bg-[#ECECEC] transition-colors"
          >
            <X size={16} />
          </button>
        </div>
        <div className="p-6">{children}</div>
      </div>
    </div>
  )
}
