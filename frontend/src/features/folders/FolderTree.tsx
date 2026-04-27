import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ChevronRight, Folder, FolderOpen, FolderPlus, Lock } from 'lucide-react'
import { getRootFolder, getFolder } from '@/api/folders'
import { cn } from '@/lib/utils'
import type { Folder as FolderType } from '@/types'

interface TreeNodeProps {
  folder: FolderType
  depth: number
  activeFolderId?: string
}

function TreeNode({ folder, depth, activeFolderId }: TreeNodeProps) {
  const navigate  = useNavigate()
  const [expanded, setExpanded] = useState(false)

  const { data } = useQuery({
    queryKey: ['folder', folder.id],
    queryFn: () => getFolder(folder.id, 1),
    enabled: expanded,
  })

  const hasChildren = expanded && (data?.subfolders.length ?? 0) > 0
  const isActive    = folder.id === activeFolderId

  return (
    <div>
      <button
        onClick={() => {
          navigate(`/folders/${folder.id}`)
          setExpanded(!expanded)
        }}
        style={{ paddingLeft: `${12 + depth * 16}px` }}
        className={cn(
          'flex items-center gap-2 w-full py-1.5 pr-3 text-sm transition-colors',
          isActive
            ? 'bg-[#FFF8E7] text-[#1A1A1A] font-medium border-l-2 border-[#F5A800]'
            : 'text-[#555] hover:bg-[#F5F5F5] border-l-2 border-transparent',
        )}
      >
        <span
          className={cn('transition-transform duration-150 shrink-0', expanded && 'rotate-90')}
          onClick={(e) => { e.stopPropagation(); setExpanded(!expanded) }}
        >
          <ChevronRight size={14} className="text-[#888]" />
        </span>

        {isActive || expanded
          ? <FolderOpen size={16} className="text-[#F5A800] shrink-0" />
          : <Folder    size={16} className="text-[#888] shrink-0" />
        }

        <span className="truncate flex-1 text-left">{folder.name}</span>

        {folder.restricted && (
          <Lock size={11} className="text-[#F5A800] shrink-0" aria-label="Accès restreint" />
        )}
      </button>

      {expanded && hasChildren && (
        <div>
          {data?.subfolders.map((sub) => (
            <TreeNode key={sub.id} folder={sub} depth={depth + 1} activeFolderId={activeFolderId} />
          ))}
        </div>
      )}
    </div>
  )
}

interface FolderTreeProps {
  onCreateFolder?: () => void
}

export function FolderTree({ onCreateFolder }: FolderTreeProps) {
  const { id: activeFolderId } = useParams()

  const { data, isLoading } = useQuery({
    queryKey: ['folder', 'root'],
    queryFn: () => getRootFolder(),
  })

  return (
    <div className="w-56 shrink-0 bg-white border-r border-[#E8E8E8] h-full overflow-y-auto">
      <div className="flex items-center justify-between px-4 py-3 border-b border-[#E8E8E8]">
        <span className="text-xs font-bold text-[#555] uppercase tracking-widest">Dossiers</span>
        {onCreateFolder && (
          <button
            onClick={onCreateFolder}
            className="p-1 rounded-md text-[#F5A800] hover:text-[#D4920A] transition-colors"
            title="Nouveau dossier"
          >
            <FolderPlus size={16} />
          </button>
        )}
      </div>
      <div className="py-2 px-2">
        {isLoading && <div className="px-3 py-2 text-xs text-[#888]">Chargement…</div>}
        {data?.subfolders.map((folder) => (
          <TreeNode key={folder.id} folder={folder} depth={0} activeFolderId={activeFolderId} />
        ))}
      </div>
    </div>
  )
}
