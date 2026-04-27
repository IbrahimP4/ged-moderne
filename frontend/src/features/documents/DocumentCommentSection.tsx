import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { MessageSquare, Trash2, Send } from 'lucide-react'
import { toast } from 'sonner'
import { getComments, addComment, deleteComment, type CommentDTO } from '@/api/documents'
import { Button } from '@/components/ui/Button'
import { useAuthStore } from '@/store/auth'
import { formatDate } from '@/lib/utils'

interface Props {
  documentId: string
}

export function DocumentCommentSection({ documentId }: Props) {
  const [content, setContent] = useState('')
  const qc = useQueryClient()
  const username = useAuthStore((s) => s.username)
  const isAdmin = useAuthStore((s) => s.isAdmin)

  const { data: comments = [] } = useQuery({
    queryKey: ['comments', documentId],
    queryFn: () => getComments(documentId),
  })

  const addMutation = useMutation({
    mutationFn: (text: string) => addComment(documentId, text),
    onSuccess: () => {
      setContent('')
      qc.invalidateQueries({ queryKey: ['comments', documentId] })
    },
    onError: () => toast.error('Impossible d\'ajouter le commentaire.'),
  })

  const deleteMutation = useMutation({
    mutationFn: (commentId: string) => deleteComment(documentId, commentId),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['comments', documentId] }),
    onError: () => toast.error('Impossible de supprimer le commentaire.'),
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    const text = content.trim()
    if (!text || addMutation.isPending) return
    addMutation.mutate(text)
  }

  return (
    <div className="bg-white rounded-lg border border-[#E8E8E8] shadow-sm overflow-hidden">
      {/* Header with yellow accent bar */}
      <div className="border-l-4 border-[#F5A800]">
        <div className="px-5 py-4 bg-[#FAFAFA] border-b border-[#F0F0F0] flex items-center gap-2">
          <MessageSquare size={16} className="text-[#F5A800]" />
          <h2 className="text-sm font-semibold text-[#1A1A1A]">Commentaires</h2>
          {comments.length > 0 && (
            <span className="bg-[#FFF3CC] text-[#A07000] text-xs font-semibold px-2 py-0.5 rounded-full">
              {comments.length}
            </span>
          )}
        </div>
      </div>

      <div className="divide-y divide-[#F0F0F0]">
        {comments.length === 0 && (
          <div className="px-5 py-8 text-center text-[#888]">
            <MessageSquare size={28} className="mx-auto mb-2 text-[#E0E0E0]" />
            <p className="text-sm">Aucun commentaire — soyez le premier !</p>
          </div>
        )}
        {comments.map((c: CommentDTO) => {
          const canDelete = isAdmin || c.username === username
          return (
            <div key={c.id} className="px-5 py-4 group hover:bg-[#FAFAFA] transition-colors">
              <div className="flex items-start gap-3">
                <div className="w-7 h-7 rounded-full bg-[#F5A800] flex items-center justify-center text-xs font-bold text-[#1A1A1A] shrink-0 mt-0.5">
                  {c.username[0]?.toUpperCase() ?? '?'}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center justify-between gap-2">
                    <span className="text-sm font-semibold text-[#1A1A1A]">{c.username}</span>
                    <div className="flex items-center gap-2">
                      <span className="text-xs text-[#888]">{formatDate(c.createdAt)}</span>
                      {canDelete && (
                        <button
                          onClick={() => deleteMutation.mutate(c.id)}
                          disabled={deleteMutation.isPending && deleteMutation.variables === c.id}
                          className="p-1 rounded text-[#CCC] hover:text-red-500 transition-colors opacity-0 group-hover:opacity-100 disabled:opacity-30"
                        >
                          <Trash2 size={13} />
                        </button>
                      )}
                    </div>
                  </div>
                  <p className="text-sm text-[#555] mt-1 leading-relaxed whitespace-pre-wrap">{c.content}</p>
                </div>
              </div>
            </div>
          )
        })}
      </div>

      <form onSubmit={handleSubmit} className="px-5 py-4 border-t border-[#F0F0F0] flex gap-3">
        <textarea
          value={content}
          onChange={(e) => setContent(e.target.value)}
          placeholder="Ajouter un commentaire…"
          rows={2}
          maxLength={2000}
          className="flex-1 px-3 py-2 text-sm border border-[#E0E0E0] rounded-md resize-none focus:outline-none focus:ring-2 focus:ring-[#F5A800] placeholder-[#AAA]"
          onKeyDown={(e) => {
            if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) handleSubmit(e as any)
          }}
        />
        <Button
          type="submit"
          size="sm"
          loading={addMutation.isPending}
          disabled={!content.trim()}
          className="self-end"
        >
          <Send size={14} />
        </Button>
      </form>
    </div>
  )
}
