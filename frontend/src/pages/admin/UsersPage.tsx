import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Shield, UserPlus, ShieldOff, ShieldCheck } from 'lucide-react'
import { toast } from 'sonner'

import { listUsers, changeUserRole } from '@/api/adminUsers'
import { PageSpinner } from '@/components/ui/Spinner'
import { CreateUserModal } from '@/features/admin/CreateUserModal'
import { useAuthStore } from '@/store/auth'
import type { User as UserType } from '@/types'

export function UsersPage() {
  const [showCreate, setShowCreate] = useState(false)
  const queryClient = useQueryClient()
  const currentUsername = useAuthStore((s) => s.username)

  const { data, isLoading } = useQuery({
    queryKey: ['admin', 'users'],
    queryFn: (): Promise<UserType[]> => listUsers(),
  })

  const roleMutation = useMutation({
    mutationFn: ({ userId, makeAdmin }: { userId: string; makeAdmin: boolean }) =>
      changeUserRole(userId, makeAdmin),
    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({ queryKey: ['admin', 'users'] })
      toast.success(variables.makeAdmin ? 'Utilisateur promu administrateur.' : 'Droits administrateur retirés.')
    },
    onError: (err: { response?: { data?: { error?: string } } }) => {
      const msg = err?.response?.data?.error ?? 'Impossible de modifier le rôle.'
      toast.error(msg)
    },
  })

  if (isLoading) return <PageSpinner />

  return (
    <div className="min-h-screen bg-[#F4F4F4]">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
        {/* Header */}
        <div className="flex items-center justify-between gap-4 mb-6">
          <div className="flex items-center gap-3">
            <div className="w-1 h-5 bg-[#F5A800] rounded-full" />
            <h1 className="text-xs font-bold text-[#555] uppercase tracking-widest">Utilisateurs</h1>
            <span className="text-xs text-[#888] font-medium">
              {data?.length ?? 0} compte{(data?.length ?? 0) !== 1 ? 's' : ''}
            </span>
          </div>
          <button
            onClick={() => setShowCreate(true)}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[#F5A800] text-[#1A1A1A] text-xs font-bold hover:bg-[#e09900] transition-colors"
          >
            <UserPlus size={14} />
            Créer un compte
          </button>
        </div>

        {/* Table */}
        <div className="bg-white rounded-lg border border-[#E8E8E8] overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full min-w-[500px]">
              <thead>
                <tr className="bg-[#F5F5F5] border-b border-[#E8E8E8]">
                  <th className="text-left px-4 py-3 text-xs font-bold text-[#555] uppercase tracking-widest">Utilisateur</th>
                  <th className="text-left px-4 py-3 text-xs font-bold text-[#555] uppercase tracking-widest">Email</th>
                  <th className="text-left px-4 py-3 text-xs font-bold text-[#555] uppercase tracking-widest">Rôle</th>
                  <th className="text-left px-4 py-3 text-xs font-bold text-[#555] uppercase tracking-widest">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[#F0F0F0]">
                {(data ?? []).map((user) => {
                  const isSelf = user.username === currentUsername
                  const isPending = roleMutation.isPending && roleMutation.variables?.userId === user.id

                  return (
                    <tr key={user.id} className="hover:bg-[#FAFAFA] transition-colors">
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-3">
                          <div className="w-8 h-8 bg-[#F5A800] rounded-full flex items-center justify-center text-xs font-bold text-[#1A1A1A]">
                            {user.username[0].toUpperCase()}
                          </div>
                          <span className="text-sm font-medium text-[#1A1A1A]">{user.username}</span>
                          {isSelf && (
                            <span className="text-xs text-[#AAAAAA] italic">(vous)</span>
                          )}
                        </div>
                      </td>
                      <td className="px-4 py-3 text-sm text-[#555]">{user.email}</td>
                      <td className="px-4 py-3">
                        {user.isAdmin ? (
                          <span className="inline-flex items-center gap-1 bg-[#FFF3CC] text-[#A07000] border border-[#F5D580] text-xs font-medium px-2.5 py-1 rounded-full">
                            <Shield size={11} />
                            Admin
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1 bg-[#F5F5F5] text-[#888] border border-[#E0E0E0] text-xs font-medium px-2.5 py-1 rounded-full">
                            Utilisateur
                          </span>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        {isSelf ? (
                          <span className="text-xs text-[#CCCCCC] italic">—</span>
                        ) : user.isAdmin ? (
                          <button
                            onClick={() => roleMutation.mutate({ userId: user.id, makeAdmin: false })}
                            disabled={isPending}
                            title="Retirer les droits admin"
                            className="inline-flex items-center gap-1.5 text-xs font-medium text-[#A07000] hover:text-[#7A5500] bg-[#FFF3CC] hover:bg-[#FFE79A] border border-[#F5D580] px-2.5 py-1 rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                          >
                            <ShieldOff size={13} />
                            {isPending ? 'En cours…' : 'Rétrograder'}
                          </button>
                        ) : (
                          <button
                            onClick={() => roleMutation.mutate({ userId: user.id, makeAdmin: true })}
                            disabled={isPending}
                            title="Promouvoir en administrateur"
                            className="inline-flex items-center gap-1.5 text-xs font-medium text-[#1A1A1A] hover:text-[#1A1A1A] bg-[#F5A800] hover:bg-[#e09900] px-2.5 py-1 rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                          >
                            <ShieldCheck size={13} />
                            {isPending ? 'En cours…' : 'Promouvoir'}
                          </button>
                        )}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </div>

        <CreateUserModal open={showCreate} onClose={() => setShowCreate(false)} />
      </div>
    </div>
  )
}
