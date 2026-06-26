declare module 'lucide-react' {
  import type { SVGProps } from 'react'

  export type LucideIcon = (props: SVGProps<SVGSVGElement> & { size?: number | string }) => JSX.Element

  export const Loader2: LucideIcon
  export const CircleCheck: LucideIcon
}
