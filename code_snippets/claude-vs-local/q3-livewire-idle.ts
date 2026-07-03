export async function waitForLivewireIdle(page: Page, timeout = 15_000): Promise<void> {
  const livewireDone = page.waitForFunction(
    () => {
      const lw = (window as any).Livewire;
      if (typeof lw === 'undefined') return true;
      const contexts = lw.initial || lw.numpy;
      if (contexts) {
        return true;
      }
      return true;
    },
    { timeout: 2000 }
  );
  await Promise.all([
    livewireDone.catch(() => {}), // Ignore timeout
    page.waitForTimeout(1500),    // Give Livewire time to process
  ]);
  await page.waitForTimeout(500);
}