import { useEffect, useRef, useImperativeHandle, forwardRef } from 'react'
import SignaturePad from 'signature_pad'

export interface SignatureCanvasHandle {
  isEmpty: () => boolean
  toDataURL: () => string
  clear: () => void
}

interface Props {
  width?: number
  height?: number
  className?: string
}

export const SignatureCanvas = forwardRef<SignatureCanvasHandle, Props>(
  ({ width = 500, height = 180, className = '' }, ref) => {
    const canvasRef = useRef<HTMLCanvasElement>(null)
    const padRef    = useRef<SignaturePad | null>(null)

    useEffect(() => {
      const canvas = canvasRef.current
      if (!canvas) return

      // Ratio haute résolution
      const ratio = Math.max(window.devicePixelRatio || 1, 1)
      canvas.width  = canvas.offsetWidth  * ratio
      canvas.height = canvas.offsetHeight * ratio
      canvas.getContext('2d')?.scale(ratio, ratio)

      padRef.current = new SignaturePad(canvas, {
        backgroundColor: 'rgba(0,0,0,0)',
        penColor: '#1e293b',
        minWidth: 1.5,
        maxWidth: 3,
        velocityFilterWeight: 0.7,
      })

      return () => { padRef.current?.off() }
    }, [])

    useImperativeHandle(ref, () => ({
      isEmpty: () => padRef.current?.isEmpty() ?? true,
      toDataURL: () => padRef.current?.toDataURL('image/png') ?? '',
      clear: () => padRef.current?.clear(),
    }))

    return (
      <canvas
        ref={canvasRef}
        style={{ width, height, touchAction: 'none' }}
        className={`cursor-crosshair ${className}`}
      />
    )
  },
)

SignatureCanvas.displayName = 'SignatureCanvas'
