/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './*.php',
    './pages/**/*.php',
    './partials/**/*.php',
    './assets/js/**/*.js',
  ],
  // Status classes are built from API values at runtime (e.g. 'pill-' + status), so keep them.
  safelist: [
    { pattern: /^pill-(active|draft|settling|closed|pending|awaiting|settled|disputed|unassigned|suspended)$/ },
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#f0fdfa',
          100: '#ccfbf1',
          200: '#99f6e4',
          300: '#5eead4',
          400: '#2dd4bf',
          500: '#14b8a6',
          600: '#0d9488',
          700: '#0f766e',
          800: '#115e59',
          900: '#134e4a',
        },
        accent: { 500: '#22c55e', 600: '#16a34a' },
        ink: '#0f172a',
        canvas: '#f1f5f5',
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      keyframes: {
        vfscan: { '0%, 100%': { top: '40px' }, '50%': { top: 'calc(100% - 60px)' } },
        nudge: { '0%, 100%': { transform: 'translateX(0)' }, '25%': { transform: 'translateX(-5px)' }, '75%': { transform: 'translateX(5px)' } },
      },
      animation: {
        vfscan: 'vfscan 1.8s ease-in-out infinite',
        nudge: 'nudge .3s ease-in-out 2',
      },
    },
  },
  plugins: [],
};
