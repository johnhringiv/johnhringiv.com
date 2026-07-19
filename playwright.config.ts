import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright configuration for johnhringiv.com E2E tests
 *
 * See https://playwright.dev/docs/test-configuration
 */
export default defineConfig({
  // Test directory
  testDir: './tests/e2e',

  // Run tests in files in parallel
  fullyParallel: true,

  // Fail the build on CI if you accidentally left test.only in the source code
  forbidOnly: !!process.env.CI,

  // Retry on CI only
  retries: process.env.CI ? 2 : 0,

  // Parallel workers: 20 locally for fast execution, 4 in CI (ubuntu-latest has 4 vCPUs)
  workers: process.env.CI ? 4 : 20,

  // Reporter configuration
  reporter: [
    ['list'],
    ['html', { outputFolder: '.build/playwright-report', open: 'never' }],
    ['json', { outputFile: '.build/test-results/results.json' }]
  ],

  // Shared settings for all projects
  use: {
    // Base URL of the container under test; override with BASE_URL if the
    // default port is taken (scripts/test-simple.sh passes this through)
    baseURL: process.env.BASE_URL || 'http://localhost:8082',

    // Capture trace on first retry
    trace: 'on-first-retry',

    // Screenshot on failure (video disabled - screenshots are sufficient)
    screenshot: 'only-on-failure',

    // Maximum time for each action (click, fill, etc.)
    actionTimeout: 10000,
  },

  // Global timeout for each test
  timeout: 30000,

  // Global timeout for the whole test run (CI runs fewer workers, so allow longer)
  globalTimeout: process.env.CI ? 2400000 : 600000, // 40 minutes on CI, 10 locally

  // Expect timeout for assertions
  expect: {
    timeout: 5000,
  },

  // Configure projects for different browsers
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },

    // Mobile viewports for responsive testing
    {
      name: 'mobile-chrome',
      use: { ...devices['Pixel 5'] },
    },

    // Tablet viewport
    {
      name: 'tablet',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 768, height: 1024 },
      },
    },

    // Firefox - enabled for CSP violation testing (stricter enforcement than Chrome)
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },

    // WebKit (Safari engine) - tests Safari compatibility on Linux
    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'] },
    },
  ],

  // Output folder for test artifacts
  outputDir: '.build/test-results',

  // Web server configuration (optional, if you want Playwright to start the server)
  // For Docker testing, the container should already be running
  // webServer: {
  //   command: 'docker run -p 8080:8080 johnhringiv.com:latest',
  //   port: 8080,
  //   reuseExistingServer: !process.env.CI,
  // },
});
