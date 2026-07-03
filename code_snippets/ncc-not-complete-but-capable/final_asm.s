next:
  push %rbp
  mov %rsp,%rbp
  sub $16,%rsp
  mov counter(%rip),%r10d
  mov %r10d,-4(%rbp)
  mov counter(%rip),%r10d
  mov %r10d,-8(%rbp)
  addl $1,-8(%rbp)
  mov -8(%rbp),%r10d
  mov %r10d,counter(%rip)
  mov counter(%rip),%eax
  mov %rbp,%rsp
  pop %rbp
  ret

main:
  push %rbp
  mov %rsp,%rbp
  sub $16,%rsp
  call next
  mov %eax,-4(%rbp)
  mov -4(%rbp),%r10d
  mov %r10d,-8(%rbp)
  call next
  mov %eax,-12(%rbp)
  mov -8(%rbp),%r10d
  mov %r10d,-16(%rbp)
  mov -12(%rbp),%r10d
  add %r10d,-16(%rbp)
  mov -16(%rbp),%eax
  mov %rbp,%rsp
  pop %rbp
  ret

.bss
counter:
  .zero 4
