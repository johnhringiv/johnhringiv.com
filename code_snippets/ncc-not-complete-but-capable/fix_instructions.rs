// shiki: nolinenum
// Memory-to-memory move: use R10 as intermediate
Instruction::Mov { src, dst } if src.is_memory() && dst.is_memory() => {
    new_ins.push(Instruction::Mov { src, dst: Operand::Reg(Reg::R10) });
    new_ins.push(Instruction::Mov { src: Operand::Reg(Reg::R10), dst });
}
