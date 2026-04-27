import { api } from '@/lib/axios'
import type { SignatureRequestDTO, AvailableSigner } from '@/types'

export async function getSignatureRequests(): Promise<SignatureRequestDTO[]> {
  const res = await api.get<SignatureRequestDTO[]>('/signature-requests')
  return res.data
}

export async function getPendingCount(): Promise<{ count: number }> {
  const res = await api.get<{ count: number }>('/signature-requests/pending-count')
  return res.data
}

export async function getAvailableSigners(): Promise<AvailableSigner[]> {
  const res = await api.get<AvailableSigner[]>('/signature-requests/available-signers')
  return res.data
}

export async function createSignatureRequest(payload: {
  document_id: string
  signer_id: string
  message?: string
}): Promise<SignatureRequestDTO> {
  const res = await api.post<SignatureRequestDTO>('/signature-requests', payload)
  return res.data
}

export async function signRequest(id: string, comment?: string): Promise<void> {
  await api.patch(`/signature-requests/${id}/sign`, { comment: comment ?? null })
}

export async function declineRequest(id: string, comment?: string): Promise<void> {
  await api.patch(`/signature-requests/${id}/decline`, { comment: comment ?? null })
}
