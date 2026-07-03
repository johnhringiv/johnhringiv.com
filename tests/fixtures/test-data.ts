/**
 * Centralized test data for Playwright E2E tests
 *
 * This file contains all the shared test data used across multiple test files,
 * including page paths, blog post slugs, expected canonical URLs, and other constants.
 */

/**
 * Blog post slugs (URL-friendly identifiers)
 */
export const BLOG_POST_SLUGS = [
  'ncc-not-complete-but-capable',
  'when-five-plus-five-equals-eleven',
  'a_subtle_python_threading_bug',
  'secure-scalable-home-web-hosting',
  'small-brains-big-test',
  'claude-vs-local'
] as const;

/**
 * All page paths including main pages and blog posts
 */
export const ALL_PAGE_PATHS = [
  '/',
  '/blog',
  '/research',
  '/press',
  ...BLOG_POST_SLUGS.map(slug => `/${slug}`)
] as const;

/**
 * Expected canonical URLs for all pages
 * These use the production domain and should match exactly
 */
export const EXPECTED_CANONICAL_URLS: Record<string, string> = {
  '/': 'https://johnhringiv.com/',
  '/blog': 'https://johnhringiv.com/blog',
  '/research': 'https://johnhringiv.com/research',
  '/press': 'https://johnhringiv.com/press',
  '/ncc-not-complete-but-capable': 'https://johnhringiv.com/ncc-not-complete-but-capable',
  '/when-five-plus-five-equals-eleven': 'https://johnhringiv.com/when-five-plus-five-equals-eleven',
  '/a_subtle_python_threading_bug': 'https://johnhringiv.com/a_subtle_python_threading_bug',
  '/secure-scalable-home-web-hosting': 'https://johnhringiv.com/secure-scalable-home-web-hosting',
  '/small-brains-big-test': 'https://johnhringiv.com/small-brains-big-test',
  '/claude-vs-local': 'https://johnhringiv.com/claude-vs-local'
};

/**
 * Blog post structural metadata for conditional testing
 * Only includes flags for optional features, not content validation
 */
export const BLOG_POST_METADATA = {
  'ncc-not-complete-but-capable': {
    hasCodeBlocks: true,
    hasMermaidDiagrams: true,
  },
  'when-five-plus-five-equals-eleven': {
    hasCodeBlocks: true,
    hasMermaidDiagrams: false,
  },
  'a_subtle_python_threading_bug': {
    hasCodeBlocks: true,
    hasMermaidDiagrams: false,
  },
  'secure-scalable-home-web-hosting': {
    hasCodeBlocks: true,
    hasMermaidDiagrams: false,
  },
  'small-brains-big-test': {
    hasCodeBlocks: true,
    hasMermaidDiagrams: false,
    interactiveCharts: {
      extraCss: ['/generated/cissp-charts.css'],
      extraJs: ['/generated/cissp-charts.js'],
      windowGlobals: ['CISSP_MODELS', 'CISSP_PER_QUESTION', 'CISSP_AGREEMENT'],
      chartIds: ['cissp-heatmap', 'cissp-quant', 'cissp-quant-ladder', 'cissp-scatter', 'cissp-difficulty', 'cissp-agreement'],
      dataTableIds: ['cissp-table-baseline', 'cissp-table-quant'],
    },
  },
  'claude-vs-local': {
    hasCodeBlocks: true,
    hasMermaidDiagrams: false,
  }
} as const;

/**
 * Expected asset paths for critical bundles and sprites
 */
export const CRITICAL_ASSETS = {
  cssBundle: '/generated/bundle.css',
  jsBundle: '/generated/bundle.js',
  iconSprite: '/generated/sprite.svg',
  cvPdf: 'PDF/John_Ring_CV.pdf'
} as const;

/**
 * Mobile viewport dimensions for responsive testing
 */
export const VIEWPORTS = {
  mobile: { width: 375, height: 667 },
  tablet: { width: 768, height: 1024 },
  laptop: { width: 1366, height: 768 },
  desktop: { width: 1920, height: 1080 }
} as const;

/**
 * Expected social media icons on homepage
 */
export const SOCIAL_ICONS = [
  { name: 'LinkedIn', href: 'https://www.linkedin.com/in/johnhringiv' },
  { name: 'GitHub', href: 'https://github.com/johnhringiv?tab=repositories' },
  { name: 'Email', href: 'mailto:johnhringiv@gmail.com' }
] as const;

/**
 * Navigation menu items
 */
export const NAV_ITEMS = [
  { text: 'Home', href: '/' },
  { text: 'Blog', href: '/blog' },
  { text: 'Research', href: '/research' },
  { text: 'Press', href: '/press' }
] as const;

/**
 * Open Graph image dimensions (optimal size for social media)
 */
export const OG_IMAGE_DIMENSIONS = {
  width: 1200,
  height: 630
} as const;

/**
 * Meta description length requirements (SEO best practices)
 */
export const META_DESCRIPTION_LENGTH = {
  min: 55,
  max: 200
} as const;

/**
 * Feed configuration
 */
export const FEED_CONFIG = {
  path: '/feed.php',
  contentType: 'application/atom+xml',
  minEntries: 4 // At least blog posts + research + press
} as const;
