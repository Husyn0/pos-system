/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        paper: '#F7F5F1',
        ink: '#241C17',
        roast: { DEFAULT: '#4A3226', dark: '#2E1F17', light: '#6B4E3D' },
        caramel: '#A6763D',
        herb: '#3F6E52',
        brick: '#A63D2F',
        gold: '#B8862E',
        muted: '#8A7F73',
      },
      fontFamily: {
        display: ['"Fraunces"', 'Georgia', 'serif'],
        sans: ['"Inter"', 'system-ui', 'sans-serif'],
        mono: ['"IBM Plex Mono"', 'monospace'],
      },
    },
  },
  plugins: [],
}
