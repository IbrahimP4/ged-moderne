import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Star, FileText, FolderOpen } from 'lucide-react'
import { toast } from 'sonner'
import { getFavorites, removeFavorite } from '@/api/documents'
import { PageSpinner } from '@/components/ui/Spinner'
import { usePageTitle } from '@/hooks/usePageTitle'
import { STATUS_LABELS, STATUS_COLORS } from '@/lib/utils'

export function FavoritesPage() {
  usePageTitle('Favoris')
  const qc = useQueryClient()

  const { data: docs, isLoading } = useQuery({
    queryKey: ['favorites'],
    queryFn: getFavorites,
  })

  const removeMutation = useMutation({
    mutationFn: (id: string) => removeFavorite(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['favorites'] })
      toast.success('Retiré des favoris.')
    },
    onError: () => toast.error('Impossible de retirer des favoris.'),
  })

  if (isLoading) return <PageSpinner />

  return (
    <div className="min-h-full bg-page">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 py-8">

        {/* Page header */}
        <div className="mb-6 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-1 h-8 bg-[#F5A800] rounded-full" />
            <div>
              <p className="text-xs font-bold text-secondary uppercase tracking-widest">Favoris</p>
              <p className="text-xs text-muted mt-0.5">{docs?.length ?? 0} document(s)</p>
            </div>
          </div>
          <div className="w-9 h-9 bg-[#FFF3CC] rounded-lg flex items-center justify-center">
            <Star size={18} style={{ color: '#F5A800', fill: '#F5A800' }} />
          </div>
        </div>

        {/* Content */}
        {docs?.length === 0 ? (
          <div className="bg-card rounded-lg border border-base p-14 flex flex-col items-center justify-center text-center">
            <div className="w-14 h-14 bg-[#FFF3CC] rounded-lg flex items-center justify-center mb-4">
              <Star size={28} style={{ color: '#E0E0E0' }} />
            </div>
            <p className="text-sm font-semibold" style={{ color: '#AAA' }}>Aucun favori pour l'instant</p>
            <p className="text-xs mt-1" style={{ color: '#CCC' }}>Cliquez sur l'étoile d'un document pour l'ajouter ici.</p>
          </div>
        ) : (
          <div className="bg-card rounded-lg border border-base overflow-hidden">

            {/* List header */}
            <div className="px-5 py-3 border-b border-muted bg-subtle">
              <div className="flex items-center gap-2">
                <div className="w-1 h-4 bg-[#F5A800] rounded-full" />
                <h2 className="text-xs font-bold text-secondary uppercase tracking-widest">Documents favoris</h2>
              </div>
            </div>

            <div className="divide-y divide-muted">
              {(docs ?? []).map((doc) => (
                <div
                  key={doc.id}
                  className="flex items-center gap-4 px-5 py-4 hover:bg-subtle transition-colors group"
                >
                  <div className="w-9 h-9 rounded-md bg-[#FFF3CC] flex items-center justify-center shrink-0">
                    <FileText size={17} style={{ color: '#F5A800' }} />
                  </div>

                  <div className="flex-1 min-w-0">
                    <Link
                      to={`/documents/${doc.id}`}
                      className="text-sm font-medium truncate block transition-colors"
                      style={{ color: '#1A1A1A' }}
                      onMouseEnter={(e) => (e.currentTarget.style.color = '#F5A800')}
                      onMouseLeave={(e) => (e.currentTarget.style.color = '#1A1A1A')}
                    >
                      {doc.title}
                    </Link>
                    <div className="flex items-center gap-2 mt-0.5">
                      <span
                        className={`text-xs px-2 py-0.5 rounded-full font-medium ${STATUS_COLORS[doc.status as keyof typeof STATUS_COLORS] ?? ''}`}
                        style={
                          !STATUS_COLORS[doc.status as keyof typeof STATUS_COLORS]
                            ? { backgroundColor: '#F0F0F0', color: '#666' }
                            : undefined
                        }
                      >
                        {STATUS_LABELS[doc.status as keyof typeof STATUS_LABELS] ?? doc.status}
                      </span>
                      {doc.folderId && (
                        <Link
                          to={`/folders/${doc.folderId}`}
                          className="flex items-center gap-1 text-xs transition-colors"
                          style={{ color: '#AAAAAA' }}
                          onMouseEnter={(e) => (e.currentTarget.style.color = '#F5A800')}
                          onMouseLeave={(e) => (e.currentTarget.style.color = '#AAAAAA')}
                        >
                          <FolderOpen size={11} />
                          Dossier
                        </Link>
                      )}
                    </div>
                  </div>

                  {/* Remove from favorites — star button */}
                  <button
                    onClick={() => removeMutation.mutate(doc.id)}
                    disabled={removeMutation.isPending && removeMutation.variables === doc.id}
                    className="p-2 rounded-lg transition-all opacity-0 group-hover:opacity-100 disabled:opacity-40"
                    style={{ color: '#F5A800' }}
                    title="Retirer des favoris"
                    onMouseEnter={(e) => {
                      e.currentTarget.style.backgroundColor = '#FFF3CC'
                    }}
                    onMouseLeave={(e) => {
                      e.currentTarget.style.backgroundColor = 'transparent'
                    }}
                  >
                    <Star size={16} style={{ fill: '#F5A800' }} />
                  </button>
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
