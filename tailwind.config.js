/** @type {import('tailwindcss').Config} */
const defaultTheme = require('tailwindcss/defaultTheme');

module.exports = {
  content: [
    "./**/*.{php,js}",            // Include your theme or plugin PHP and JS files
    "!./node_modules/**/*",        // Exclude everything inside node_modules
    "./node_modules/preline/dist/*.js"  // Explicitly include Preline's JS from node_modules
  ],
  theme: {
    extend: {
      fontFamily: {
        'sans': ['"Inter"', ...defaultTheme.fontFamily.sans],
      },
    }
  },
  plugins: [
    require('preline/plugin'),
    require('@tailwindcss/forms'),
  ],
}

