import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Trash2, RotateCcw, FileText } from 'lucide-react'
import { toast } from 'sonner'
import { getTrash, restoreDocument, permanentDeleteDocument, type TrashDocumentDTO } from '@/api/documents'
import { ConfirmModal } from '@/components/ui/ConfirmModal'
import { PageSpinner } from '@/components/ui/Spinner'
import { useAuthStore } from '@/store/auth'
import { usePageTitle } from '@/hooks/usePageTitle'
import { formatDate } from '@/lib/utils'

export function TrashPage() {
  usePageTitle('Corbeille')
  const isAdmin = useAuthStore((s) => s.isAdmin)
  const qc = useQueryClient()

  const [confirmDelete, setConfirmDelete] = useState<TrashDocumentDTO | null>(null)

  const { data: docs, isLoading } = useQuery({
    queryKey: ['trash'],
    queryFn: getTrash,
  })

  const restoreMutation = useMutation({
    mutationFn: (id: string) => restoreDocument(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['trash'] })
      toast.success('Document restauré.')
    },
    onError: () => toast.error('Impossible de restaurer le document.'),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => permanentDeleteDocument(id),
    onSuccess: () => {
      setConfirmDelete(null)
      qc.invalidateQueries({ queryKey: ['trash'] })
      toast.success('Document supprimé définitivement.')
    },
    onError: () => toast.error('Impossible de supprimer le document.'),
  })

  if (isLoading) return <PageSpinner />

  return (
    <div className="min-h-full bg-[#F4F4F4]">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 py-8">

        {/* Page header */}
        <div className="mb-6 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-1 h-8 bg-[#F5A800] rounded-full" />
            <div>
              <p className="text-xs font-bold text-[#555] uppercase tracking-widest">Corbeille</p>
              <p className="text-xs text-[#999] mt-0.5">{docs?.length ?? 0} document(s) supprimé(s)</p>
            </div>
          </div>
          <div className="w-9 h-9 bg-[#FEE8E8] rounded-lg flex items-center justify-center">
            <Trash2 size={18} style={{ color: '#D93025' }} />
          </div>
        </div>

        {/* Content */}
        {docs?.length === 0 ? (
          <div className="bg-white rounded-lg border border-[#E8E8E8] p-14 flex flex-col items-center justify-center text-center">
            <div className="w-14 h-14 bg-[#F5F5F5] rounded-lg flex items-center justify-center mb-4">
              <Trash2 size={28} style={{ color: '#E0E0E0' }} />
            </div>
            <p className="text-sm font-semibold" style={{ color: '#AAA' }}>La corbeille est vide</p>
            <p className="text-xs mt-1" style={{ color: '#CCC' }}>Les documents supprimés apparaîtront ici.</p>
          </div>
        ) : (
          <div className="bg-white rounded-lg border border-[#E8E8E8] overflow-hidden">

            {/* List header */}
            <div className="px-5 py-3 border-b border-[#F0F0F0] bg-[#FAFAFA]">
              <div className="flex items-center gap-2">
                <div className="w-1 h-4 bg-[#F5A800] rounded-full" />
                <h2 className="text-xs font-bold text-[#555] uppercase tracking-widest">Documents supprimés</h2>
              </div>
            </div>

            <div className="divide-y divide-[#F0F0F0]">
              {(docs ?? []).map((doc) => (
                <div
                  key={doc.id}
                  className="flex items-center gap-4 px-5 py-4 hover:bg-[#FAFAFA] transition-colors"
                >
                  <div className="w-9 h-9 rounded-md bg-[#F5F5F5] flex items-center justify-center shrink-0">
                    <FileText size={17} style={{ color: '#AAAAAA' }} />
                  </div>

                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium truncate" style={{ color: '#1A1A1A' }}>{doc.title}</p>
                    <p className="text-xs mt-0.5" style={{ color: '#AAAAAA' }}>
                      Supprimé {formatDate(doc.deletedAt)}
                    </p>
                  </div>

                  <div className="flex items-center gap-2 shrink-0">
                    {/* Restore — yellow (primary) */}
                    <button
                      onClick={() => restoreMutation.mutate(doc.id)}
                      disabled={restoreMutation.isPending && restoreMutation.variables === doc.id}
                      className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition-opacity disabled:opacity-50"
                      style={{ backgroundColor: '#F5A800', color: '#1A1A1A' }}
                    >
                      <RotateCcw size={13} />
                      Restaurer
                    </button>

                    {/* Delete — red (danger) */}
                    {isAdmin && (
                      <button
                        onClick={() => setConfirmDelete(doc)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition-opacity"
                        style={{ backgroundColor: '#FEE8E8', color: '#D93025' }}
                      >
                        <Trash2 size={13} />
                        Supprimer
                      </button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>

      <ConfirmModal
        open={confirmDelete !== null}
        onClose={() => setConfirmDelete(null)}
        onConfirm={() => confirmDelete && deleteMutation.mutate(confirmDelete.id)}
        loading={deleteMutation.isPending}
        title="Suppression définitive"
        message={`Voulez-vous supprimer définitivement "${confirmDelete?.title}" ? Cette action est irréversible.`}
        confirmLabel="Supprimer définitivement"
      />
    </div>
  )
}
