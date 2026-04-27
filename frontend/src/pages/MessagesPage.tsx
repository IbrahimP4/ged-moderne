import { useState, useEffect, useRef } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useSearchParams } from 'react-router-dom'
import {
  MessageCircle, Send, Search, Users, FileText,
  ChevronRight, ArrowLeft, Paperclip, X,
} from 'lucide-react'
import { toast } from 'sonner'
import { api } from '@/lib/axios'
import {
  getConversations,
  getConversation,
  sendMessage,
  getUsers,
  type ConversationSummary,
  type MessageDTO,
  type GedUser,
} from '@/api/realtimeApi'
import { useAuthStore } from '@/store/auth'
import { useRealtimeStore } from '@/store/realtimeStore'
import { PageSpinner } from '@/components/ui/Spinner'

// ── Hook présence ──────────────────────────────────────────────────────────

function usePresencePing() {
  useEffect(() => {
    // Ping immédiat à l'ouverture
    api.post('/presence/ping').catch(() => {})

    // Ping toutes les 30 s
    const interval = setInterval(() => {
      api.post('/presence/ping').catch(() => {})
    }, 30_000)

    return () => clearInterval(interval)
  }, [])
}

function usePartnerPresence(username: string | null) {
  return useQuery<{ username: string; online: boolean; lastSeen: string | null }>({
    queryKey: ['presence', username],
    queryFn: async () => {
      const { data } = await api.get(`/presence/${username}`)
      return data
    },
    enabled: !!username,
    refetchInterval: 30_000,
    staleTime: 25_000,
  })
}

// ── Indicateur de présence visuel ───────────────────────────────────────────────

function PresenceIndicator({ username }: { username: string }) {
  const { data } = usePartnerPresence(username)

  if (!data) return <p className="text-xs text-[#AAA]">...</p>

  if (data.online) {
    return (
      <span className="flex items-center gap-1.5 text-xs text-emerald-600 font-medium">
        <span className="w-2 h-2 bg-emerald-500 rounded-full animate-pulse" />
        En ligne
      </span>
    )
  }

  const lastSeenLabel = data.lastSeen
    ? (() => {
        const diff = Date.now() - new Date(data.lastSeen).getTime()
        if (diff < 60_000) return 'il y a quelques instants'
        if (diff < 3_600_000) return `il y a ${Math.floor(diff / 60_000)} min`
        if (diff < 86_400_000) return `aujourd'hui à ${new Date(data.lastSeen).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`
        return `le ${new Date(data.lastSeen).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })}`
      })()
    : null

  return (
    <span className="flex items-center gap-1.5 text-xs text-[#AAA]">
      <span className="w-2 h-2 bg-[#CCC] rounded-full" />
      {lastSeenLabel ? `Vu ${lastSeenLabel}` : 'Hors ligne'}
    </span>
  )
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function timeLabel(iso: string): string {
  const d    = new Date(iso)
  const now  = new Date()
  const diff = now.getTime() - d.getTime()

  if (diff < 60_000)       return 'à l\'instant'
  if (diff < 3_600_000)    return `${Math.floor(diff / 60_000)} min`
  if (diff < 86_400_000) {
    return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  }
  return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })
}

function Avatar({ username, size = 'md' }: { username: string; size?: 'sm' | 'md' | 'lg' }) {
  const cls = size === 'sm'
    ? 'w-8 h-8 text-xs rounded-md'
    : size === 'lg'
    ? 'w-11 h-11 text-base rounded-lg'
    : 'w-9 h-9 text-sm rounded-md'
  return (
    <div className={`${cls} bg-[#F5A800] flex items-center justify-center font-bold text-[#1A1A1A] shrink-0`}>
      {username[0].toUpperCase()}
    </div>
  )
}

// ── Liste des conversations ───────────────────────────────────────────────────
function ConversationList({
  conversations,
  selectedId,
  onSelect,
}: {
  conversations: ConversationSummary[]
  selectedId: string | null
  onSelect: (id: string, username: string) => void
  myUsername?: string
}) {
  const [search, setSearch] = useState('')

  const filtered = conversations.filter((c) =>
    c.partner.username.toLowerCase().includes(search.toLowerCase()),
  )

  return (
    <div className="flex flex-col h-full">
      {/* Searchbar */}
      <div className="p-3 border-b border-[#E8E8E8]">
        <div className="relative">
          <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-[#AAA]" />
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Rechercher une conversation…"
            className="w-full pl-9 pr-3 py-2 text-sm bg-white border border-[#E0E0E0] rounded-md focus:outline-none focus:ring-2 focus:ring-[#F5A800] focus:border-[#F5A800]"
          />
        </div>
      </div>

      {/* Liste */}
      <div className="flex-1 overflow-y-auto">
        {filtered.length === 0 ? (
          <div className="text-center py-10 text-sm text-[#AAA]">
            Aucune conversation
          </div>
        ) : (
          filtered.map((c) => {
            const isMe    = c.lastMessage.senderId !== c.partner.id
            const preview = isMe
              ? `Vous : ${c.lastMessage.content}`
              : c.lastMessage.content

            return (
              <button
                key={c.partner.id}
                onClick={() => onSelect(c.partner.id, c.partner.username)}
                className={`w-full flex items-center gap-3 px-4 py-3 transition-colors border-b border-[#F0F0F0] hover:bg-[#FAFAFA] ${
                  selectedId === c.partner.id
                    ? 'bg-[#FFF8E7] border-l-2 border-l-[#F5A800]'
                    : ''
                }`}
              >
                <div className="relative shrink-0">
                  <Avatar username={c.partner.username} />
                  {c.unreadCount > 0 && (
                    <span className="absolute -top-1 -right-1 w-4 h-4 bg-[#F5A800] text-[#1A1A1A] text-[10px] font-bold rounded-full flex items-center justify-center">
                      {c.unreadCount > 9 ? '9+' : c.unreadCount}
                    </span>
                  )}
                </div>
                <div className="flex-1 min-w-0 text-left">
                  <div className="flex justify-between items-baseline">
                    <span className={`text-sm font-semibold ${c.unreadCount > 0 ? 'text-[#1A1A1A]' : 'text-[#555]'}`}>
                      {c.partner.username}
                    </span>
                    <span className="text-[11px] text-[#AAA] shrink-0 ml-2">
                      {timeLabel(c.lastMessage.sentAt)}
                    </span>
                  </div>
                  <p className={`text-xs truncate mt-0.5 ${c.unreadCount > 0 ? 'text-[#1A1A1A] font-medium' : 'text-[#888]'}`}>
                    {preview}
                  </p>
                </div>
              </button>
            )
          })
        )}
      </div>
    </div>
  )
}

// ── Bulle de message ──────────────────────────────────────────────────────────
function MessageBubble({ msg, isMe }: { msg: MessageDTO; isMe: boolean }) {
  return (
    <div className={`flex items-end gap-2 ${isMe ? 'flex-row-reverse' : 'flex-row'} mb-3`}>
      {!isMe && (
        <Avatar username={msg.senderUsername} size="sm" />
      )}
      <div className={`max-w-[68%] flex flex-col gap-1 ${isMe ? 'items-end' : 'items-start'}`}>
        {/* Document partagé */}
        {msg.documentId && (
          <a
            href={`/documents/${msg.documentId}`}
            className="flex items-center gap-2 text-xs bg-white border border-[#E8E8E8] text-[#1A1A1A] rounded-md px-3 py-1.5 hover:border-[#F5A800] transition-colors"
          >
            <FileText size={12} className="text-[#F5A800]" />
            <span className="truncate max-w-[160px]">{msg.documentTitle ?? 'Document'}</span>
            <ChevronRight size={10} />
          </a>
        )}
        {/* Texte */}
        <div
          className={`px-4 py-2.5 rounded-md text-sm leading-relaxed ${
            isMe
              ? 'bg-[#1A1A1A] text-white rounded-br-sm'
              : 'bg-[#F5F5F5] text-[#1A1A1A] rounded-bl-sm'
          }`}
        >
          {msg.content}
        </div>
        <span className="text-[10px] text-[#AAA] px-1">
          {timeLabel(msg.sentAt)}
        </span>
      </div>
    </div>
  )
}

// ── Sélecteur de document ─────────────────────────────────────────────────────
function DocumentPicker({
  onSelect,
  onClose,
}: {
  onSelect: (id: string, title: string) => void
  onClose: () => void
}) {
  const [q, setQ] = useState('')

  const { data: results = [], isLoading } = useQuery<
    { id: string; title: string; mimeType: string; status: string }[]
  >({
    queryKey: ['doc-picker', q],
    queryFn: async () => {
      const { data } = await api.get('/documents', { params: { q, limit: 20 } })
      return data as { id: string; title: string; mimeType: string; status: string }[]
    },
    staleTime: 10_000,
  })

  return (
    <div className="absolute bottom-full left-0 mb-2 w-80 bg-white rounded-lg shadow-2xl border border-[#E8E8E8] z-50 overflow-hidden">
      <div className="px-3 py-3 border-b border-[#E8E8E8] flex items-center justify-between">
        <span className="text-sm font-semibold text-[#1A1A1A]">Joindre un document</span>
        <button onClick={onClose} className="text-[#AAA] hover:text-[#555]">
          <X size={16} />
        </button>
      </div>
      <div className="p-2">
        <div className="relative mb-2">
          <Search size={13} className="absolute left-3 top-1/2 -translate-y-1/2 text-[#AAA]" />
          <input
            autoFocus
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Rechercher un document…"
            className="w-full pl-8 pr-3 py-2 text-sm bg-white border border-[#E0E0E0] rounded-md focus:outline-none focus:ring-2 focus:ring-[#F5A800] focus:border-[#F5A800]"
          />
        </div>
        <div className="max-h-56 overflow-y-auto space-y-0.5">
          {isLoading && (
            <p className="text-xs text-[#AAA] text-center py-4">Recherche…</p>
          )}
          {!isLoading && results.length === 0 && (
            <p className="text-xs text-[#AAA] text-center py-4">Aucun document trouvé</p>
          )}
          {results.map((doc) => (
            <button
              key={doc.id}
              onClick={() => { onSelect(doc.id, doc.title); onClose() }}
              className="w-full flex items-center gap-2.5 px-3 py-2 rounded-md hover:bg-[#FFF8E7] transition-colors text-left"
            >
              <div className="w-7 h-7 bg-[#F5A800] rounded-md flex items-center justify-center shrink-0">
                <FileText size={14} className="text-[#1A1A1A]" />
              </div>
              <div className="min-w-0">
                <p className="text-sm font-medium text-[#1A1A1A] truncate">{doc.title}</p>
                <p className="text-[11px] text-[#AAA] truncate">{doc.mimeType}</p>
              </div>
            </button>
          ))}
        </div>
      </div>
    </div>
  )
}

// ── Thread de conversation ────────────────────────────────────────────────────
function ChatThread({
  partnerId,
  partnerUsername,
  myId,
}: {
  partnerId: string
  partnerUsername: string
  myId: string
}) {
  const [text, setText]               = useState('')
  const [showPicker, setShowPicker]   = useState(false)
  const [attachedDoc, setAttachedDoc] = useState<{ id: string; title: string } | null>(null)
  const bottomRef                     = useRef<HTMLDivElement>(null)
  const qc                            = useQueryClient()

  const { data: messages = [], isLoading } = useQuery({
    queryKey: ['conversation', partnerId],
    queryFn: () => getConversation(partnerId),
    refetchInterval: 4_000,
    staleTime: 0,
  })

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages])

  useEffect(() => {
    qc.invalidateQueries({ queryKey: ['conversations'] })
  }, [partnerId]) // eslint-disable-line react-hooks/exhaustive-deps

  const sendMut = useMutation({
    mutationFn: () =>
      sendMessage({
        recipient_id:   partnerId,
        content:        text.trim() || (attachedDoc ? `📎 ${attachedDoc.title}` : ''),
        document_id:    attachedDoc?.id,
        document_title: attachedDoc?.title,
      }),
    onSuccess: (newMsg) => {
      qc.setQueryData<MessageDTO[]>(['conversation', partnerId], (old = []) => [...old, newMsg])
      qc.invalidateQueries({ queryKey: ['conversations'] })
      setText('')
      setAttachedDoc(null)
    },
    onError: () => toast.error('Impossible d\'envoyer le message.'),
  })

  const canSend = (text.trim() !== '' || attachedDoc !== null) && !sendMut.isPending

  const handleSend = () => { if (canSend) sendMut.mutate() }
  const handleKey  = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSend() }
  }

  if (isLoading) return <PageSpinner />

  return (
    <div className="flex flex-col h-full">
      {/* Header avec indicateur de présence */}
      <div className="flex items-center gap-3 px-5 py-4 border-b border-[#E8E8E8] bg-white shrink-0">
        <Avatar username={partnerUsername} />
        <div>
          <p className="text-sm font-bold text-[#1A1A1A]">{partnerUsername}</p>
          <PresenceIndicator username={partnerUsername} />
        </div>
      </div>

      {/* Messages */}
      <div className="flex-1 overflow-y-auto px-5 py-4 bg-[#F4F4F4]">
        {messages.length === 0 && (
          <div className="text-center py-10 text-[#AAA]">
            <MessageCircle size={36} className="mx-auto mb-3 text-[#DDD]" />
            <p className="text-sm">Démarrez la conversation !</p>
          </div>
        )}
        {messages.map((msg) => (
          <MessageBubble key={msg.id} msg={msg} isMe={msg.senderId === myId} />
        ))}
        <div ref={bottomRef} />
      </div>

      {/* Zone de saisie */}
      <div className="px-4 py-3 border-t border-[#E8E8E8] bg-white shrink-0">

        {/* Document attaché */}
        {attachedDoc && (
          <div className="flex items-center gap-2 mb-2 bg-[#FFF8E7] border border-[#F5A800] rounded-md px-3 py-2">
            <FileText size={14} className="text-[#F5A800] shrink-0" />
            <span className="text-xs font-medium text-[#1A1A1A] flex-1 truncate">{attachedDoc.title}</span>
            <button
              onClick={() => setAttachedDoc(null)}
              className="text-[#AAA] hover:text-[#555]"
            >
              <X size={14} />
            </button>
          </div>
        )}

        <div className="flex items-end gap-2">
          {/* Bouton joindre document */}
          <div className="relative shrink-0">
            <button
              onClick={() => setShowPicker((v) => !v)}
              className={`w-10 h-10 rounded-md flex items-center justify-center transition-colors ${
                showPicker || attachedDoc
                  ? 'bg-[#FFF8E7] text-[#F5A800]'
                  : 'bg-[#F4F4F4] text-[#888] hover:bg-[#E8E8E8]'
              }`}
              title="Joindre un document"
            >
              <Paperclip size={16} />
            </button>
            {showPicker && (
              <DocumentPicker
                onSelect={(id, title) => setAttachedDoc({ id, title })}
                onClose={() => setShowPicker(false)}
              />
            )}
          </div>

          {/* Textarea */}
          <div className="flex-1 bg-white border border-[#E0E0E0] rounded-md px-4 py-2.5 focus-within:ring-2 focus-within:ring-[#F5A800] focus-within:border-[#F5A800] transition-all">
            <textarea
              value={text}
              onChange={(e) => setText(e.target.value)}
              onKeyDown={handleKey}
              placeholder={attachedDoc ? 'Ajouter un message (facultatif)…' : `Écrire à ${partnerUsername}…`}
              rows={1}
              className="w-full bg-transparent text-sm text-[#1A1A1A] placeholder-[#AAA] resize-none outline-none max-h-32 leading-relaxed"
              style={{ fieldSizing: 'content' } as React.CSSProperties}
            />
          </div>

          {/* Envoyer */}
          <button
            onClick={handleSend}
            disabled={!canSend}
            className="w-10 h-10 bg-[#F5A800] hover:bg-[#E09700] text-[#1A1A1A] rounded-md flex items-center justify-center transition-colors disabled:opacity-40 disabled:cursor-not-allowed shrink-0"
          >
            <Send size={16} />
          </button>
        </div>
        <p className="text-[10px] text-[#AAA] mt-1.5 pl-1">
          Entrée pour envoyer · Shift+Entrée pour saut de ligne
        </p>
      </div>
    </div>
  )
}

// ── Page principale ───────────────────────────────────────────────────────────
export function MessagesPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const initialWith = searchParams.get('with')

  // Heartbeat de présence — marque l'utilisateur comme "en ligne"
  usePresencePing()

  const [selectedId, setSelectedId]       = useState<string | null>(initialWith)
  const [selectedName, setSelectedName]   = useState<string>('')
  const [showNewConv, setShowNewConv]     = useState(false)
  const [userSearch, setUserSearch]       = useState('')
  const [mobileView, setMobileView]       = useState<'list' | 'chat'>('list')

  const qc          = useQueryClient()
  const myId        = useAuthStore((s) => s.username) ?? ''
  const myIdFromStore = useAuthStore((s) => s.username) ?? ''

  const { data: conversations = [], isLoading: convLoading } = useQuery({
    queryKey: ['conversations'],
    queryFn:  getConversations,
    refetchInterval: 4_000,
    staleTime: 0,
  })

  const { data: allUsers = [] } = useQuery({
    queryKey: ['users'],
    queryFn:  getUsers,
    enabled:  showNewConv,
    staleTime: 60_000,
  })

  // Récupère l'username quand on ouvre depuis ?with=
  useEffect(() => {
    if (initialWith && conversations.length > 0) {
      const found = conversations.find((c) => c.partner.id === initialWith)
      if (found) {
        setSelectedName(found.partner.username)
        setMobileView('chat')
      }
    }
  }, [initialWith, conversations])

  // Rafraîchit les conversations quand un message SSE arrive
  const unreadMessages = useRealtimeStore((s) => s.unreadMessages)
  useEffect(() => {
    qc.invalidateQueries({ queryKey: ['conversations'] })
    if (selectedId) {
      qc.invalidateQueries({ queryKey: ['conversation', selectedId] })
    }
  }, [unreadMessages]) // eslint-disable-line react-hooks/exhaustive-deps

  const handleSelect = (id: string, username: string) => {
    setSelectedId(id)
    setSelectedName(username)
    setMobileView('chat')
    setSearchParams({})
  }

  const filteredUsers = allUsers.filter((u: GedUser) =>
    u.username.toLowerCase().includes(userSearch.toLowerCase()),
  )

  if (convLoading) return <PageSpinner />

  return (
    <div className="h-full flex overflow-hidden bg-[#F4F4F4]">
      {/* ── Colonne gauche : liste des conversations ── */}
      <div className={`flex flex-col bg-white border-r border-[#E8E8E8] ${
        mobileView === 'chat' ? 'hidden md:flex' : 'flex'
      } w-full md:w-72 lg:w-80 shrink-0`}>
        {/* Header */}
        <div className="px-4 py-4 border-b border-[#E8E8E8]">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <MessageCircle size={18} className="text-[#F5A800]" />
              <h1 className="text-base font-bold text-[#1A1A1A]">Messages</h1>
            </div>
            <button
              onClick={() => setShowNewConv(true)}
              className="w-8 h-8 bg-[#F5A800] hover:bg-[#E09700] text-[#1A1A1A] rounded-md flex items-center justify-center transition-colors"
              title="Nouvelle conversation"
            >
              <Users size={14} />
            </button>
          </div>
        </div>

        {conversations.length === 0 ? (
          <div className="flex-1 flex flex-col items-center justify-center text-center p-6">
            <MessageCircle size={40} className="text-[#DDD] mb-3" />
            <p className="text-sm font-medium text-[#555]">Aucune conversation</p>
            <p className="text-xs text-[#AAA] mt-1 mb-4">Démarrez une nouvelle conversation</p>
            <button
              onClick={() => setShowNewConv(true)}
              className="flex items-center gap-2 bg-[#F5A800] hover:bg-[#E09700] text-[#1A1A1A] text-sm font-medium px-4 py-2 rounded-md transition-colors"
            >
              <Users size={14} /> Nouveau message
            </button>
          </div>
        ) : (
          <ConversationList
            conversations={conversations}
            selectedId={selectedId}
            onSelect={handleSelect}
            myUsername={myIdFromStore}
          />
        )}
      </div>

      {/* ── Zone principale : thread ou placeholder ── */}
      <div className={`flex-1 flex flex-col min-w-0 ${
        mobileView === 'list' ? 'hidden md:flex' : 'flex'
      }`}>
        {selectedId ? (
          <>
            {/* Bouton retour mobile */}
            <button
              onClick={() => setMobileView('list')}
              className="md:hidden flex items-center gap-2 text-sm text-[#F5A800] px-4 py-2 border-b border-[#E8E8E8] bg-white"
            >
              <ArrowLeft size={16} /> Retour
            </button>
            <ChatThread
              partnerId={selectedId}
              partnerUsername={selectedName}
              myId={myId}
            />
          </>
        ) : (
          <div className="flex-1 flex flex-col items-center justify-center text-center p-8 bg-[#F4F4F4]">
            <div className="w-20 h-20 bg-[#F5A800] rounded-lg flex items-center justify-center mb-4">
              <MessageCircle size={36} className="text-[#1A1A1A]" />
            </div>
            <h2 className="text-lg font-bold text-[#1A1A1A]">Messagerie GED</h2>
            <p className="text-sm text-[#888] mt-2 max-w-xs">
              Sélectionnez une conversation ou démarrez-en une nouvelle pour communiquer avec vos collègues.
            </p>
            <button
              onClick={() => setShowNewConv(true)}
              className="mt-5 flex items-center gap-2 bg-[#F5A800] hover:bg-[#E09700] text-[#1A1A1A] text-sm font-medium px-5 py-2.5 rounded-md transition-colors"
            >
              <Users size={15} /> Nouvelle conversation
            </button>
          </div>
        )}
      </div>

      {/* ── Modal : choisir un interlocuteur ── */}
      {showNewConv && (
        <div className="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-2xl w-full max-w-sm overflow-hidden border border-[#E8E8E8]">
            <div className="px-5 py-4 border-b border-[#E8E8E8] flex items-center justify-between">
              <h3 className="text-base font-bold text-[#1A1A1A]">Nouvelle conversation</h3>
              <button
                onClick={() => { setShowNewConv(false); setUserSearch('') }}
                className="text-[#AAA] hover:text-[#555] text-xl leading-none"
              >
                ×
              </button>
            </div>
            <div className="p-4">
              <div className="relative mb-3">
                <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-[#AAA]" />
                <input
                  autoFocus
                  value={userSearch}
                  onChange={(e) => setUserSearch(e.target.value)}
                  placeholder="Rechercher un utilisateur…"
                  className="w-full pl-9 pr-3 py-2 text-sm bg-white border border-[#E0E0E0] rounded-md focus:outline-none focus:ring-2 focus:ring-[#F5A800] focus:border-[#F5A800]"
                />
              </div>
              <div className="max-h-64 overflow-y-auto space-y-1">
                {filteredUsers.map((u: GedUser) => (
                  <button
                    key={u.id}
                    onClick={() => {
                      handleSelect(u.id, u.username)
                      setShowNewConv(false)
                      setUserSearch('')
                    }}
                    className="w-full flex items-center gap-3 px-3 py-2.5 rounded-md hover:bg-[#FFF8E7] transition-colors text-left"
                  >
                    <Avatar username={u.username} size="sm" />
                    <div>
                      <p className="text-sm font-semibold text-[#1A1A1A]">{u.username}</p>
                      {u.isAdmin && (
                        <p className="text-[11px] text-[#F5A800] font-medium">Administrateur</p>
                      )}
                    </div>
                  </button>
                ))}
                {filteredUsers.length === 0 && (
                  <p className="text-sm text-[#AAA] text-center py-4">
                    Aucun utilisateur trouvé
                  </p>
                )}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
