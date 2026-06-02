/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './app/**/*.{js,jsx,ts,tsx}',
    './components/**/*.{js,jsx,ts,tsx}',
  ],
  presets: [require('nativewind/preset')],
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: '#1A1A2E',
          dark: '#12122A',
          light: '#2A2A4E',
        },
        accent: {
          DEFAULT: '#E8A020',
          dark: '#C8860C',
          light: '#F5C060',
        },
        surface: '#FFFFFF',
        background: '#F5F5F7',
        muted: '#8E8E93',
        border: '#E5E5EA',
        success: '#34C759',
        warning: '#FF9500',
        error: '#FF3B30',
      },
      fontFamily: {
        sans: ['Inter_400Regular'],
        medium: ['Inter_500Medium'],
        semibold: ['Inter_600SemiBold'],
        bold: ['Inter_700Bold'],
        heading: ['PlayfairDisplay_700Bold'],
      },
    },
  },
  plugins: [],
};
