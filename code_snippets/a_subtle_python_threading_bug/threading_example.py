import threading
import numpy as np

def case1(num_threads = 8):
    nums = np.arange(2000)
    results = []
    threads = [
        threading.Thread(target=lambda: results.append(sum(batch)))
         for batch in np.array_split(nums, num_threads)
    ]

    for t in threads:
        t.start()

    for t in threads:
        t.join()
    return sum(results)