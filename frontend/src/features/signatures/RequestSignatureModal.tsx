import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { FileSignature, User } from 'lucide-react'
import { createSignatureRequest, getAvailableSigners } from '@/api/signatureRequests'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'

interface Props {
  open: boolean
  onClose: () => void
  documentId: string
  documentTitle: string
}

export function RequestSignatureModal({ open, onClose, documentId, documentTitle }: Props) {
  const qc = useQueryClient()
  const [signerId, setSignerId] = useState('')
  const [message, setMessage] = useState('')

  const { data: signers, isLoading: loadingSigners } = useQuery({
    queryKey: ['available-signers'],
    queryFn: getAvailableSigners,
    enabled: open,
  })

  const mutation = useMutation({
    mutationFn: () => createSignatureRequest({
      document_id: documentId,
      signer_id: signerId,
      message: message.trim() || undefined,
    }),
    onSuccess: () => {
      toast.success('Demande de signature envoyée !')
      qc.invalidateQueries({ queryKey: ['signature-requests'] })
      qc.invalidateQueries({ queryKey: ['signature-pending-count'] })
      setSignerId('')
      setMessage('')
      onClose()
    },
    onError: () => toast.error('Impossible d\'envoyer la demande.'),
  })

  const handleClose = () => {
    if (mutation.isPending) return
    setSignerId('')
    setMessage('')
    onClose()
  }

  return (
    <Modal open={open} onClose={handleClose} title="Demander une signature">
      <div className="space-y-4">
        {/* Document info */}
        <div className="flex items-center gap-3 p-3 bg-indigo-50 rounded-xl border border-indigo-100">
          <FileSignature size={20} className="text-indigo-500 shrink-0" />
          <div>
            <p className="text-xs text-indigo-600 font-medium">Document</p>
            <p className="text-sm font-semibold text-gray-900 leading-tight">{documentTitle}</p>
          </div>
        </div>

        {/* Signer selector */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1.5">
            Signataire <span className="text-red-500">*</span>
          </label>
          {loadingSigners ? (
            <p className="text-sm text-gray-400 py-2">Chargement des signataires…</p>
          ) : signers && signers.length > 0 ? (
            <div className="space-y-2">
              {signers.map((s) => (
                <label
                  key={s.id}
                  className={`flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all ${
                    signerId === s.id
                      ? 'border-indigo-400 bg-indigo-50 ring-1 ring-indigo-400'
                      : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'
                  }`}
                >
                  <input
                    type="radio"
                    name="signer"
                    value={s.id}
                    checked={signerId === s.id}
                    onChange={() => setSignerId(s.id)}
                    className="hidden"
                  />
                  <div className="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white text-sm font-bold shrink-0">
                    {s.username[0].toUpperCase()}
                  </div>
                  <div>
                    <p className="text-sm font-medium text-gray-800">{s.username}</p>
                    <p className="text-xs text-indigo-600">Administrateur</p>
                  </div>
                  {signerId === s.id && (
                    <span className="ml-auto text-indigo-500 text-xs font-semibold">✓ Sélectionné</span>
                  )}
                </label>
              ))}
            </div>
          ) : (
            <div className="flex items-center gap-2 p-3 bg-amber-50 border border-amber-200 rounded-xl">
              <User size={16} className="text-amber-500" />
              <p className="text-sm text-amber-700">Aucun administrateur disponible comme signataire.</p>
            </div>
          )}
        </div>

        {/* Message */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1.5">
            Message <span className="text-gray-400 font-normal">(optionnel)</span>
          </label>
          <textarea
            value={message}
            onChange={(e) => setMessage(e.target.value)}
            rows={3}
            maxLength={500}
            placeholder="Contexte, urgence, instructions particulières…"
            className="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
          />
          <p className="text-xs text-gray-400 mt-1 text-right">{message.length}/500</p>
        </div>

        <div className="flex justify-end gap-2 pt-1">
          <Button variant="secondary" onClick={handleClose}>Annuler</Button>
          <Button
            onClick={() => mutation.mutate()}
            loading={mutation.isPending}
            disabled={!signerId || (signers?.length === 0)}
          >
            <FileSignature size={15} />
            Envoyer la demande
          </Button>
        </div>
      </div>
    </Modal>
  )
}
