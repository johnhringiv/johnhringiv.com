#shiki: nolinenum
>>> x = [lambda i=i: i for i in range(5)]
>>> [f() for f in x]
[0, 1, 2, 3, 4]