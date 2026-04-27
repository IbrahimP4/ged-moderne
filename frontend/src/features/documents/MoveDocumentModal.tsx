import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { FolderOpen, ChevronRight, Loader2 } from 'lucide-react'
import { getFolder, getRootFolder } from '@/api/folders'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import type { Folder } from '@/types'

interface Props {
  open: boolean
  onClose: () => void
  onConfirm: (folderId: string) => void
  loading?: boolean
  currentFolderId: string
}

function FolderItem({
  folder,
  selectedId,
  onSelect,
  currentFolderId,
}: {
  folder: Folder
  selectedId: string | null
  onSelect: (f: Folder) => void
  currentFolderId: string
}) {
  const [expanded, setExpanded] = useState(false)
  const { data, isFetching } = useQuery({
    queryKey: ['folder', folder.id],
    queryFn: () => getFolder(folder.id, 1),
    enabled: expanded,
  })

  const isDisabled = folder.id === currentFolderId

  return (
    <div>
      <div
        className={`flex items-center gap-2 px-3 py-2 rounded-lg cursor-pointer transition-colors ${
          isDisabled ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-100'
        } ${selectedId === folder.id ? 'bg-indigo-50 ring-1 ring-indigo-300' : ''}`}
        onClick={() => { if (!isDisabled) onSelect(folder) }}
      >
        <button
          type="button"
          className="text-gray-400 hover:text-gray-600 w-4 shrink-0"
          onClick={(e) => { e.stopPropagation(); setExpanded(!expanded) }}
        >
          <ChevronRight size={14} className={`transition-transform ${expanded ? 'rotate-90' : ''}`} />
        </button>
        <FolderOpen size={16} className="text-amber-400 shrink-0" />
        <span className="text-sm text-gray-800">{folder.name}</span>
        {isDisabled && <span className="text-xs text-gray-400 ml-auto">(dossier actuel)</span>}
      </div>
      {expanded && (
        <div className="ml-5 border-l border-gray-200 pl-2 mt-0.5">
          {isFetching && <Loader2 size={14} className="animate-spin text-gray-400 ml-3 my-1" />}
          {data?.subfolders.map((sub) => (
            <FolderItem
              key={sub.id}
              folder={sub}
              selectedId={selectedId}
              onSelect={onSelect}
              currentFolderId={currentFolderId}
            />
          ))}
        </div>
      )}
    </div>
  )
}

export function MoveDocumentModal({ open, onClose, onConfirm, loading, currentFolderId }: Props) {
  const [selectedFolder, setSelectedFolder] = useState<Folder | null>(null)

  const { data: root, isLoading } = useQuery({
    queryKey: ['folder', 'root'],
    queryFn: () => getRootFolder(),
    enabled: open,
  })

  return (
    <Modal open={open} onClose={onClose} title="Déplacer le document" className="max-w-md">
      <div className="space-y-4">
        <p className="text-sm text-gray-500">Choisissez le dossier de destination :</p>

        <div className="border border-gray-200 rounded-xl max-h-64 overflow-y-auto p-2">
          {isLoading && (
            <div className="flex justify-center py-4">
              <Loader2 size={20} className="animate-spin text-gray-400" />
            </div>
          )}
          {root && (
            <>
              <div
                className={`flex items-center gap-2 px-3 py-2 rounded-lg cursor-pointer transition-colors hover:bg-gray-100 ${
                  selectedFolder?.id === root.folder.id ? 'bg-indigo-50 ring-1 ring-indigo-300' : ''
                } ${root.folder.id === currentFolderId ? 'opacity-40 cursor-not-allowed' : ''}`}
                onClick={() => {
                  if (root.folder.id !== currentFolderId) setSelectedFolder(root.folder)
                }}
              >
                <FolderOpen size={16} className="text-indigo-400 shrink-0" />
                <span className="text-sm font-medium text-gray-800">{root.folder.name}</span>
                {root.folder.id === currentFolderId && (
                  <span className="text-xs text-gray-400 ml-auto">(dossier actuel)</span>
                )}
              </div>
              {root.subfolders.map((folder) => (
                <FolderItem
                  key={folder.id}
                  folder={folder}
                  selectedId={selectedFolder?.id ?? null}
                  onSelect={setSelectedFolder}
                  currentFolderId={currentFolderId}
                />
              ))}
            </>
          )}
        </div>

        {selectedFolder && (
          <p className="text-xs text-indigo-600 font-medium">
            → {selectedFolder.name}
          </p>
        )}

        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose} disabled={loading}>Annuler</Button>
          <Button
            onClick={() => selectedFolder && onConfirm(selectedFolder.id)}
            loading={loading}
            disabled={!selectedFolder}
          >
            Déplacer
          </Button>
        </div>
      </div>
    </Modal>
  )
}
