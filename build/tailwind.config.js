/** DogoFlow Tailwind build config (extracted from the old CDN config). */
module.exports = Object.assign({
  corePlugins: { container: false },
  theme: {
    extend: {
      colors: {
        // Brand palette — identical hex values to the original app.css tokens
        brand:   { DEFAULT: '#00A3FF', hover: '#0088EE', deep: '#0A1628', light: '#33B5FF', soft: '#E6F6FF' },
        navy:    { DEFAULT: '#0A1628', surface: '#0F2137', card: '#132A45', mid: '#0F4C81' },
        gold:    { DEFAULT: '#F59E0B', premium: '#D4AF37', light: '#FEF3C7', dark: '#D97706' },
        burgundy: '#800020',
        rosegold: '#B76E79',
        success: { DEFAULT: '#10B981', light: '#D1FAE5' },
        danger:  { DEFAULT: '#F43F5E', light: '#FFE4E6' },
        info:    { DEFAULT: '#3B82F6', light: '#DBEAFE' },
        ink:     { DEFAULT: '#111827', muted: '#6B7280', light: '#9CA3AF' },
        canvas:  '#FAFAFC',
        soft:    '#F4F4F8',
        line:    '#E5E7EB',
        edge:    '#D8E4F0',
        frame:   '#DCE6F0',
        heading: '#0B2545',
        mist:    '#F3F7FB',
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'system-ui', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'sans-serif'],
      },
      borderRadius: { 'df-sm': '10px', 'df-md': '18px', 'df-lg': '28px' },
      boxShadow: {
        soft: '0 2px 8px rgba(10,22,40,.06)',
        lift: '0 8px 24px rgba(10,22,40,.10)',
        deep: '0 16px 32px rgba(10,22,40,.14)',
        glow: '0 10px 25px -5px rgba(0,163,255,.4)',
        'glow-lg': '0 14px 28px -4px rgba(0,163,255,.5)',
        gold: '0 8px 20px rgba(245,158,11,.3)',
        panel: '0 8px 24px rgba(7,26,51,.055)',
        'panel-sm': '0 4px 18px rgba(10,22,40,.04)',
        auth: '0 18px 55px rgba(10,22,40,.10)',
      },
      backgroundImage: {
        'brand-gradient': 'linear-gradient(135deg,#00A3FF,#0088EE)',
        'gold-gradient': 'linear-gradient(135deg,#F59E0B,#D97706)',
        'wallet-gradient': 'linear-gradient(135deg,#0A1628,#12304F)',
        'avatar-gradient': 'linear-gradient(135deg,#00A3FF,#0068A8)',
        'hero-gradient': 'linear-gradient(135deg,rgba(10,22,40,.92) 0%,rgba(0,136,238,.78) 100%),linear-gradient(135deg,#0A1628,#0F4C81)',
        'sidebar-gradient': 'linear-gradient(180deg,#071A33 0%,#0A2B4E 58%,#0B3A66 100%)',
        'auth-glow': 'radial-gradient(circle at top right,rgba(0,163,255,.11),transparent 32%)',
      },
    },
  },
}, {
  content: ['../public_html/**/*.php', '../includes/**/*.php', '../core/**/*.php'],
});
