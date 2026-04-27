import { AlertTriangle } from 'lucide-react'
import { Modal } from './Modal'
import { Button } from './Button'

interface ConfirmModalProps {
  open: boolean
  onClose: () => void
  onConfirm: () => void
  title?: string
  message: string
  confirmLabel?: string
  variant?: 'danger' | 'primary'
  loading?: boolean
}

export function ConfirmModal({
  open,
  onClose,
  onConfirm,
  title = 'Confirmation',
  message,
  confirmLabel = 'Confirmer',
  variant = 'danger',
  loading,
}: ConfirmModalProps) {
  return (
    <Modal open={open} onClose={onClose} title={title}>
      <div className="space-y-5">
        <div className="flex items-start gap-4">
          <div className={`w-10 h-10 rounded-md flex items-center justify-center shrink-0 ${
            variant === 'danger' ? 'bg-red-50 border border-red-100' : 'bg-[#FFF3CC] border border-[#F5C842]'
          }`}>
            <AlertTriangle size={18} className={variant === 'danger' ? 'text-red-600' : 'text-[#F5A800]'} />
          </div>
          <p className="text-sm text-[#444444] leading-relaxed pt-1.5">{message}</p>
        </div>
        <div className="flex justify-end gap-2 pt-1 border-t border-[#F0F0F0]">
          <Button variant="secondary" onClick={onClose} disabled={loading}>Annuler</Button>
          <Button variant={variant} onClick={onConfirm} loading={loading}>{confirmLabel}</Button>
        </div>
      </div>
    </Modal>
  )
}
