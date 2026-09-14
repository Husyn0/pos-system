/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        paper: '#F7F5F1',
        ink: '#241C17',
        roast: { DEFAULT: '#4A3226', dark: '#2E1F17' },
        caramel: '#A6763D',
        herb: '#3F6E52',
        muted: '#8A7F73',
      },
      fontFamily: {
        display: ['"Fraunces"', 'serif'],
        sans: ['"Inter"', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
