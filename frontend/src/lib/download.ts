import { api } from '@/lib/axios'
import { toast } from 'sonner'

function readFilename(contentDisposition?: string): string | null {
  if (!contentDisposition) return null

  const utf8Match = contentDisposition.match(/filename\*=UTF-8''([^;]+)/i)
  if (utf8Match?.[1]) return decodeURIComponent(utf8Match[1])

  const simpleMatch = contentDisposition.match(/filename=\"?([^"]+)\"?/i)
  return simpleMatch?.[1] ?? null
}

export async function downloadAuthenticatedFile(url: string, fallbackFilename: string): Promise<void> {
  try {
    const targetUrl = url.startsWith('/api') ? url.slice(4) : url
    const response = await api.get<Blob>(targetUrl, { responseType: 'blob' })
    const blobUrl = window.URL.createObjectURL(response.data)
    const filename = readFilename(response.headers['content-disposition']) ?? fallbackFilename

    const link = document.createElement('a')
    link.href = blobUrl
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()

    window.URL.revokeObjectURL(blobUrl)
  } catch (error) {
    toast.error('Erreur lors du téléchargement.')
    console.error('Download error:', error)
  }
}
