import { useCallback, useRef } from 'react'

/**
 * Son de notification généré via Web Audio API (zéro dépendance externe).
 * Produit un "ding" doux à deux tons, professionnel et discret.
 */
export function useNotificationSound() {
  const ctx = useRef<AudioContext | null>(null)

  const playSound = useCallback((variant: 'notification' | 'message' = 'notification') => {
    try {
      if (!ctx.current) {
        ctx.current = new AudioContext()
      }
      const ac = ctx.current

      if (ac.state === 'suspended') {
        ac.resume()
      }

      const now = ac.currentTime

      if (variant === 'message') {
        // Deux notes courtes "pop" pour les messages
        playTone(ac, 880, now,       0.06, 0.08)
        playTone(ac, 1100, now + 0.1, 0.04, 0.07)
      } else {
        // Ding doux et agréable pour les notifications
        playTone(ac, 660, now,       0.08, 0.15)
        playTone(ac, 880, now + 0.14, 0.05, 0.12)
      }
    } catch {
      // Navigateur sans Web Audio ou politique autoplay — on ignore silencieusement
    }
  }, [])

  return { playSound }
}

function playTone(
  ac: AudioContext,
  freq: number,
  startTime: number,
  volume: number,
  duration: number,
): void {
  const osc  = ac.createOscillator()
  const gain = ac.createGain()

  osc.connect(gain)
  gain.connect(ac.destination)

  osc.type      = 'sine'
  osc.frequency.setValueAtTime(freq, startTime)

  gain.gain.setValueAtTime(0, startTime)
  gain.gain.linearRampToValueAtTime(volume, startTime + 0.01)
  gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration)

  osc.start(startTime)
  osc.stop(startTime + duration)
}
