import { api } from '@/lib/axios'

export interface ProfileSignatureDTO {
  dataUrl: string
  type: 'drawn' | 'uploaded'
  updatedAt: string
}

export interface CompanyStampDTO {
  dataUrl: string
  updatedAt: string
}

export async function getProfileSignature(): Promise<ProfileSignatureDTO | null> {
  const res = await api.get<ProfileSignatureDTO | null>('/profile/signature')
  return res.data
}

export async function saveProfileSignature(dataUrl: string, type: 'drawn' | 'uploaded'): Promise<ProfileSignatureDTO> {
  const res = await api.post<ProfileSignatureDTO>('/profile/signature', { dataUrl, type })
  return res.data
}

export async function deleteProfileSignature(): Promise<void> {
  await api.delete('/profile/signature')
}

export async function getCompanyStamp(): Promise<CompanyStampDTO | null> {
  const res = await api.get<CompanyStampDTO | null>('/company-stamp')
  return res.data
}

export async function saveCompanyStamp(dataUrl: string): Promise<CompanyStampDTO> {
  const res = await api.post<CompanyStampDTO>('/company-stamp', { dataUrl })
  return res.data
}

export async function deleteCompanyStamp(): Promise<void> {
  await api.delete('/company-stamp')
}
