import { lazy, Suspense } from 'react'
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ReactQueryDevtools } from '@tanstack/react-query-devtools'
import { Toaster } from 'sonner'
import { AppLayout } from '@/components/layout/AppLayout'
import { LoginPage } from '@/features/auth/LoginPage'
import { PageSpinner } from '@/components/ui/Spinner'
import { useAuthStore } from '@/store/auth'

// ── Lazy-loaded pages ────────────────────────────────────────────────────────
const FolderPage            = lazy(() => import('@/pages/FolderPage').then((m) => ({ default: m.FolderPage })))
const DocumentPage          = lazy(() => import('@/pages/DocumentPage').then((m) => ({ default: m.DocumentPage })))
const SearchPage            = lazy(() => import('@/pages/SearchPage').then((m) => ({ default: m.SearchPage })))
const DashboardPage         = lazy(() => import('@/pages/DashboardPage').then((m) => ({ default: m.DashboardPage })))
const MySignaturesPage      = lazy(() => import('@/pages/MySignaturesPage').then((m) => ({ default: m.MySignaturesPage })))
const ProfilePage           = lazy(() => import('@/pages/ProfilePage').then((m) => ({ default: m.ProfilePage })))
const MessagesPage          = lazy(() => import('@/pages/MessagesPage').then((m) => ({ default: m.MessagesPage })))
const TrashPage             = lazy(() => import('@/pages/TrashPage').then((m) => ({ default: m.TrashPage })))
const FavoritesPage         = lazy(() => import('@/pages/FavoritesPage').then((m) => ({ default: m.FavoritesPage })))
const UsersPage             = lazy(() => import('@/pages/admin/UsersPage').then((m) => ({ default: m.UsersPage })))
const AuditPage             = lazy(() => import('@/pages/admin/AuditPage').then((m) => ({ default: m.AuditPage })))
const SignatureRequestsPage = lazy(() => import('@/pages/admin/SignatureRequestsPage').then((m) => ({ default: m.SignatureRequestsPage })))

// ── Query client ─────────────────────────────────────────────────────────────
const queryClient = new QueryClient({
  defaultOptions: {
    queries: { staleTime: 30_000, retry: 1 },
  },
})

function AdminGuard({ children }: { children: React.ReactNode }) {
  const isAdmin = useAuthStore((s) => s.isAdmin)
  if (!isAdmin) return <Navigate to="/folders" replace />
  return <>{children}</>
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Suspense fallback={<PageSpinner />}>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route element={<AppLayout />}>
              <Route index element={<Navigate to="/folders" replace />} />
              <Route path="/folders"      element={<FolderPage />} />
              <Route path="/folders/:id"  element={<FolderPage />} />
              <Route path="/documents/:id" element={<DocumentPage />} />
              <Route path="/search"       element={<SearchPage />} />
              <Route path="/dashboard"    element={<DashboardPage />} />
              <Route path="/my-signatures" element={<MySignaturesPage />} />
              <Route path="/messages"     element={<MessagesPage />} />
              <Route path="/profile"      element={<ProfilePage />} />
              <Route path="/trash"        element={<TrashPage />} />
              <Route path="/favorites"    element={<FavoritesPage />} />
              <Route path="/admin/signatures" element={<AdminGuard><SignatureRequestsPage /></AdminGuard>} />
              <Route path="/admin/users"      element={<AdminGuard><UsersPage /></AdminGuard>} />
              <Route path="/admin/audit"      element={<AdminGuard><AuditPage /></AdminGuard>} />
            </Route>
            <Route path="*" element={<Navigate to="/folders" replace />} />
          </Routes>
        </Suspense>
      </BrowserRouter>
      <Toaster
        position="bottom-right"
        toastOptions={{
          duration: 3500,
          classNames: { toast: 'text-sm font-medium' },
        }}
        richColors
      />
      <ReactQueryDevtools initialIsOpen={false} />
    </QueryClientProvider>
  )
}
