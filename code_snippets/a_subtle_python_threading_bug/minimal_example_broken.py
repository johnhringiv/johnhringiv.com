#shiki: nolinenum
>>> x = [lambda: i for i in range(5)]
>>> [f() for f in x]
[4, 4, 4, 4, 4]