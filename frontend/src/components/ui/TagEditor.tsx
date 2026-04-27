import { useState, useRef } from 'react'
import { Plus } from 'lucide-react'
import { TagBadge } from './TagBadge'

interface TagEditorProps {
  tags: string[]
  onChange: (tags: string[]) => void
  disabled?: boolean
}

export function TagEditor({ tags, onChange, disabled }: TagEditorProps) {
  const [input, setInput] = useState('')
  const inputRef = useRef<HTMLInputElement>(null)

  const addTag = (value: string) => {
    const tag = value.trim().toLowerCase()
    if (!tag || tag.length > 50) return
    if (tags.includes(tag)) {
      setInput('')
      return
    }
    onChange([...tags, tag])
    setInput('')
  }

  const removeTag = (tag: string) => {
    onChange(tags.filter((t) => t !== tag))
  }

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault()
      addTag(input)
    } else if (e.key === 'Backspace' && input === '' && tags.length > 0) {
      onChange(tags.slice(0, -1))
    }
  }

  return (
    <div
      className="flex flex-wrap gap-1.5 min-h-[36px] px-3 py-2 rounded-lg border border-gray-300 bg-white cursor-text focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-transparent transition-shadow"
      onClick={() => inputRef.current?.focus()}
    >
      {tags.map((tag) => (
        <TagBadge
          key={tag}
          tag={tag}
          onRemove={disabled ? undefined : () => removeTag(tag)}
        />
      ))}
      {!disabled && (
        <div className="flex items-center gap-1 flex-1 min-w-[100px]">
          <Plus size={12} className="text-gray-400 shrink-0" />
          <input
            ref={inputRef}
            value={input}
            onChange={(e) => setInput(e.target.value)}
            onKeyDown={handleKeyDown}
            onBlur={() => { if (input.trim()) addTag(input) }}
            placeholder={tags.length === 0 ? 'Ajouter un tag… (Entrée ou virgule)' : ''}
            className="flex-1 text-xs outline-none bg-transparent text-gray-700 placeholder-gray-400"
            disabled={disabled}
          />
        </div>
      )}
    </div>
  )
}
