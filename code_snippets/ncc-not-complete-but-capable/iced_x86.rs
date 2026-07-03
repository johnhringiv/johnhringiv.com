// shiki: nolinenum
let mut asm = CodeAssembler::new(64)?;
asm.mov(rax, rbp - 8)?;   // Local variable
asm.add(rax, rcx)?;
asm.mov(rbp - 16, rax)?;
...