#!/usr/bin/env bash
set -euo pipefail

###############################################################################
# Simple Test Runner
# Just builds, tests, and shows results - nothing fancy
###############################################################################

IMAGE_NAME="johnhringiv.com:latest"
CONTAINER_NAME="johnhringiv-test"
VALIDATOR_NAME="johnhringiv-validator"
VALIDATOR_IMAGE="ghcr.io/validator/validator"
PORT="${PORT:-8082}"   # override if 8082 is taken: PORT=8083 npm run test:docker
VALIDATOR_STARTED=false

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  Docker Build & Test"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Cleanup function
cleanup() {
  docker stop "$CONTAINER_NAME" 2>/dev/null || true
  docker rm "$CONTAINER_NAME" 2>/dev/null || true

  if [ "$VALIDATOR_STARTED" = true ]; then
    echo "Stopping validator..."
    docker stop "$VALIDATOR_NAME" 2>/dev/null || true
    docker rm "$VALIDATOR_NAME" 2>/dev/null || true
  fi
}

trap cleanup EXIT

# Start HTML validator (W3C Nu validator, served over HTTP on :8888)
echo "Starting HTML validator..."

# Validator runs as a Docker container — no Java/jar needed (Docker is already
# required by this script). Same servlet the vnu.jar exposes, same :8888 API.
if ! command -v docker &> /dev/null; then
  echo "✗ Error: Docker is not installed"
  exit 1
fi

# Reuse if a validator is already answering on 8888; otherwise start our own
if curl -sf http://localhost:8888 > /dev/null 2>&1; then
  echo "✓ Validator already running"
else
  docker rm -f "$VALIDATOR_NAME" 2>/dev/null || true
  docker run -d --name "$VALIDATOR_NAME" -p 8888:8888 "$VALIDATOR_IMAGE" > /dev/null
  VALIDATOR_STARTED=true

  # Wait for the servlet to come up (image pull happens inside docker run above)
  for i in {1..30}; do
    if curl -sf http://localhost:8888 > /dev/null 2>&1; then
      echo "✓ Validator started ($VALIDATOR_NAME)"
      break
    fi
    sleep 1
    if [ $i -eq 30 ]; then
      echo "✗ Error: Validator failed to start"
      echo "  Check logs: docker logs $VALIDATOR_NAME"
      exit 1
    fi
  done
fi

echo ""

# Cleanup old container
docker stop "$CONTAINER_NAME" 2>/dev/null || true
docker rm "$CONTAINER_NAME" 2>/dev/null || true

# Build
echo "Building Docker image..."
docker build -t "$IMAGE_NAME" . -q
echo "✓ Build complete"
echo ""

# Start container
echo "Starting container on port $PORT..."
docker run -d -p "$PORT:8080" --name "$CONTAINER_NAME" "$IMAGE_NAME" > /dev/null
sleep 3
echo "✓ Container running"
echo ""

# Run tests
echo "Running tests..."
echo ""
set +e  # Disable exit-on-error to capture exit code
BASE_URL="http://localhost:$PORT" npm run test:e2e:quiet
TEST_EXIT=$?
set -e  # Re-enable exit-on-error

echo ""
# Parse JSON results and print structured summary
TEST_EXIT=$TEST_EXIT node -e "
const fs = require('fs');
const jsonPath = '.build/test-results/results.json';

if (!fs.existsSync(jsonPath)) {
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log('  ⚠️  WARNING: Could not read test results JSON');
  console.log('  ' + (process.env.TEST_EXIT === '0' ? '✓ Tests appear to have passed' : '✗ Tests failed'));
  console.log('  View detailed report: npm run test:report');
  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  process.exit(Number(process.env.TEST_EXIT) || 0);
}

const results = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
const stats = results.stats || {};

const total = stats.expected + stats.unexpected + stats.flaky + stats.skipped || 0;
const passed = stats.expected || 0;
const failed = stats.unexpected || 0;
const skipped = stats.skipped || 0;
const flaky = stats.flaky || 0;
const duration = Math.round((stats.duration || 0) / 1000) + 's';

console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
console.log('  ' + total + ' tests: ' + passed + ' passed, ' + failed + ' failed, ' + skipped + ' skipped, ' + flaky + ' flaky');
console.log('  Duration: ' + duration);

if (failed === 0) {
  console.log('');
  console.log('  ✓ ALL TESTS PASSED');
} else {
  console.log('');
  console.log('  ✗ TESTS FAILED');
  console.log('');

  // List failed tests
  const failedTests = [];
  if (results.suites) {
    function collectFailed(suites) {
      for (const suite of suites) {
        if (suite.specs) {
          for (const spec of suite.specs) {
            if (spec.tests) {
              for (const test of spec.tests) {
                if (test.status === 'unexpected') {
                  const project = test.projectName || 'unknown';
                  const location = spec.file + ':' + (spec.line || '?');
                  failedTests.push('    - [' + project + '] ' + location + ' \"' + spec.title + '\"');
                }
              }
            }
          }
        }
        if (suite.suites) collectFailed(suite.suites);
      }
    }
    collectFailed(results.suites);

    if (failedTests.length > 0) {
      console.log('  Failed:');
      failedTests.slice(0, 10).forEach(t => console.log(t));
      if (failedTests.length > 10) {
        console.log('    ... and ' + (failedTests.length - 10) + ' more');
      }
    }
  }
  console.log('');
  console.log('  View detailed report: npm run test:report');
}
console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

process.exit(failed > 0 ? 1 : 0);
"
exit $?
