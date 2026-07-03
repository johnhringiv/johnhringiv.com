export async function waitForLivewireIdle(page: Page, timeout = 10_000): Promise<void> {
  await page.waitForFunction(
    () => {
      const wire = (window as any).Livewire;
      if (!wire) return true;
      return true;
    },
    { timeout },
  );
  await page.waitForLoadState('networkidle');
}