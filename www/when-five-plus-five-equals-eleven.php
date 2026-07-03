<?php
require_once "includes/classes.php";

$page_info = PageInfo::fromDB('when-five-plus-five-equals-eleven');

include_once "includes/top.php";
?>

<div class="container blog-post pb-2">
    <article>
        <!-- Post header-->
        <?php
        $page_info->renderFullHeader();
        ?>

        <!-- Post content-->
        <div class="mb-5">
            <?php echo $page_info->html_description ?>
        </div>
        <section>
            <h2 id="what-is-undefined-behavior" class="fw-bolder mb-4 mt-5">
                <a href="#what-is-undefined-behavior" class="text-reset text-decoration-none">What is Undefined Behavior</a>
            </h2>
            <p>
                The C standard defines what constitutes a valid C program and how that program should behave.
                However, the standard leaves some gaps in its specification.
                There are two categories of what I'll call underspecified behavior:
            <p class="lead shadow-sm py-2 ps-2 rounded-3">
                <b class="fs-5">Unspecified Behavior:</b> Cases where there are multiple valid behaviors, and the compiler can choose any of them.
            </p>
            <p>
                An example of this is the order of evaluation of operands within binary expressions.
                The standard does not mandate whether the left or right operand is evaluated first.
                In this case the compiler can choose any order, and this choice does not have to be consistent.
                However, the only valid choice for the compiler is to evaluate those operands then evaluate the expression.
            </p>
            <p class="lead shadow-sm py-2 ps-2 rounded-3">
                <b class="fs-5">Undefined Behavior:</b> The standard provides no requirements.
            </p>
            <p>
                In this case the compiler can do whatever it wants.
                The <i>likely</i> outcome is that the compiler picks a behavior most programmers would expect, but it's under no obligation to do so.
                This may seem ridiculous at first, but it's not without good reason.
                Another way to think about undefined behavior is it's the standards way of telling the compiler writer "These situations do not occur, proceed as you wish".
                By being able to ignore edge cases that well crafted C programs should avoid enables the compiler to make aggressive optimizations; desirable for programs not leveraging undefined behavior.
                For example the compiler can unroll loops in cases where it can't guarantee that the induction variable won't overflow as the responsibility to avoid overflow, an undefined behavior, is on the programmer.
                If you want to learn more about this I highly recommend this <a href="https://blog.llvm.org/2011/05/what-every-c-programmer-should-know.html">blog post</a>.
            </p>
            <p>
                Furthermore, many cases of undefined behavior cannot be detected statically.
                Runtime checks could be inserted, but they would add significant overhead to all C programs.
                A simple example of this is accessing an array out of bounds.
                We can easily insert bounds checks but this adds overhead to all array accesses.
            </p>
        </section>
        
        <section>
            <h2 id="undefined-behavior-in-practice" class="fw-bolder mb-4 mt-5">
                <a href="#undefined-behavior-in-practice" class="text-reset text-decoration-none">Undefined Behavior in Practice</a>
            </h2>
            <p>
                Undefined behavior is a painful part of C programming responsible for many bugs and security vulnerabilities.
                C is often described as a small and simple language however the ambiguity that comes with it adds significant complexity for the end user.
                The decades since C was created have taught us that programmers aren't particularly good at avoiding undefined behavior and much of computer science has focused on how to make programming safer.
                As an aside, I agree that C is a small Language but would argue that the traditionally considered more <i>"complex"</i> languages like Rust are actually simpler to use due to their more rigorous specifications.
            </p>
            <p>
                There are many forms of undefined behavior in C.
                This post will mostly focus on evaluation order in expressions, but we'll touch on a few others at the end.
                You might be surprised to learn that even the simple snippet below is enough to get inconsistent results.
            </p>
            <?php include "generated/highlighted-shiki/when-five-plus-five-equals-eleven/main_test.html"; ?>

            <p>
                In standard C, expressions like <code>i + i++</code> exhibit <em>undefined behavior</em>. The C standard doesn't specify:
            </p>
            <ul>
                <li>Whether the left or right operand is evaluated first</li>
                <li>When side effects (like the increment in <code>i++</code>) take effect</li>
            </ul>
            <p>
                With that in mind let's take another look at the snippet above.
            </p>

            <p class="fw-bold mb-1">With Left-to-Right</p>
            <ol>
                <li>Evaluate the left <code>i</code> → 5</li>
                <li>Evaluate <code>i++</code>  → 5, then increment <code>i</code></li>
                <li>Add: 5 + 5 = 10</li>
            </ol>

            <p class="fw-bold mb-1">With Right-to-Left</p>
            <ol>
                <li>Evaluate <code>i++</code>  → 5, then increment <code>i</code></li>
                <li>Evaluate the left <code>i</code> → 6</li>
                <li>Add: 5 + 6 = 11</li>
            </ol>

            <p>
                On GCC we return <code>11</code>, while on Clang we get <code>10</code>.
                Both compilers issue warnings about unsequenced modifications and accesses to <code>i</code>, Clang by default with <code>-Wunsequenced</code> and GCC with <code>-Wsequence-point</code> if warnings are enabled.
            <p>
                There's a few things we need understand here.
            </p>
            <ul>
                <li>The evaluation order of operands in binary expressions is <strong>unspecified</strong> (not undefined), meaning the compiler can choose any order it wants.</li>
                <li>We are <i>accessing</i> (left hand side), and <i>modifying</i> (right hand side), the same object <code>i</code></li>
                <li>There is no sequence point in between the access and the modification, which leads to undefined behavior.</li>
            </ul>
            <p>
                This is called a <strong>sequence point violation</strong>, and these often occur in non-contrived examples.
                Function calls with side effects like <code>f() + g()</code> can violate sequence points if both modify the same state.
                Array indexing expressions such as <code>arr[i] = i++</code> are another common pitfall.
                These patterns appear frequently in real code, making sequence point violations one of C's most insidious sources of bugs.
            </p>
            <h3 id="standardizing-evaluation-order" class="fs-4 fw-bolder mb-3 mt-4">
                <a href="#standardizing-evaluation-order" class="text-reset text-decoration-none">Standardizing Evaluation Order</a>
            </h3>
            <p>
                By standardizing the evaluation order, we can eliminate this source of undefined behavior.
                A strict evaluation order creates an implicit sequence point between the evaluation of the left and right operands.
                If only all cases of undefined behavior were this easy to solve!
            </p>
            <p>
                Naively, one (like myself), may think that this is a trivial problem to solve, just constantly evaluate the left side first.
                As you'll see that approach is <i>almost</i> sufficient.
            </p>
            <h3 id="why-doesnt-c-do-this" class="fs-4 fw-bolder mb-3 mt-4">
                <a href="#why-doesnt-c-do-this" class="text-reset text-decoration-none">Why Doesn't C Do this?</a>
            </h3>
            <p>
                Sequencing evaluation order does come with a hidden cost.
                Register allocation becomes more difficult as the compiler must ensure that the left hand side is fully evaluated and stored before evaluating the right hand side.
                Other optimization opportunities are also lost; for example, vectorization may require reordering of operations, prevented with this approach.
            </p>
            <p>
                Sequencing is certainly the simplest solution, but it's not the only way to solve this problem.
                Rust, like C, does not sequence evaluation order.
                However, Rust prevents sequence point violations at compile time via the borrow checker.
                This is a more complex solution, allowing full optimization opportunities while avoiding undefined behavior.
            </p>
            <p>
                Basic cases like our example above can be detected with static analysis, but more complex cases involving pointers and aliasing range from difficult to impossible to detect.
                For example, consider the following:
            </p>
            <?php include "generated/highlighted-shiki/when-five-plus-five-equals-eleven/uncaught_sequence.html"; ?>
            <p>
                Neither GCC or Clang warn about this (still quite simple) example.
            </p>
            <p>
                We can't retrofit a borrow checker into C due to core language constructs like pointer arithmetic.
                <strong>Safety has to be a core part of the language design</strong>; something I'll keep in mind if I finish this project and make a language of my own.
            </p>

            <h2 id="enter-ncc" class="fw-bolder mb-4 mt-5">
                <a href="#enter-ncc" class="text-reset text-decoration-none">Enter NCC</a>
            </h2>
            <p>
                Enter <strong>NCC</strong> - Not Completely C my hobby C compiler written in Rust.
                NCC implements a substantial subset of C's control flow and expression evaluation within a single function scope.
                It roughly follows <i>Writing a C Compiler</i> by Nora Sandler.
                An excellent read proposing an iterative approach to compiler design.
                The book describes core concepts of building a compiler without spoon-feeding the reader an implementation.
                Already, many of the features NCC implements are suggested by but otherwise not covered in the book including pre/post fix operators, compound assignment, bitwise operators and goto statements.
                The most concise way I can describe what's currently implemented is with the formal grammar, so without further ado:
            </p>
            <?php include "generated/highlighted-shiki/when-five-plus-five-equals-eleven/grammar.html"; ?>
            <p>
                Don't worry if this doesn't mean anything to you.
                Basically, I've implemented all major control flow statements and operators allowing the compilation of real programs.
                The major missing features are functions, structs, arrays, and pointers.
                The code is available on <a href="https://github.com/johnhringiv/NCC-Rust">Github</a>.
            </p>

            <h2 id="how-does-ncc-handle-this" class="fw-bolder mb-4 mt-5">
                <a href="#how-does-ncc-handle-this" class="text-reset text-decoration-none">How does NCC handle this?</a>
            </h2>
            
            <p>
                With NCC we aim to enforce a strict left-to-right evaluation order for expressions.
                Since NCC evaluates the left hand side first, I expected it to return 10, <strong>but it was returning 11!</strong>
                As far as this C standard is concerned, this is a valid result.
                It's also one of two <i>reasonable</i> results in my opinion.
                That said, it's not what I expected, or want, so let's fix it.
            </p>

            <h2 id="debugging-ncc" class="fw-bolder mb-4 mt-5">
                <a href="#debugging-ncc" class="text-reset text-decoration-none">Debugging NCC</a>
            </h2>
            <p>
                NCC has a fairly comprehensive tests suite that was passing before I started this investigation.
                This gives me confidence that all the operators (inc. <code>++</code>) are working correctly.
            </p>
            
            <h3 class="fs-5">Evaluation Order Verification</h3>
            <p>
                Since we're enforcing a strict left-to-right evaluation order, we should have a test case for that behavior.
                I didn't have much doubt that this was working in the current implementation but it's important to keep in mind when adding optimization passes.
            </p>
            <?php include "generated/highlighted-shiki/when-five-plus-five-equals-eleven/test4_left_first.html"; ?>
            <div class="test-pass">
                This test detects evaluation order - returning 5 for left-to-right, 7 for right-to-left.
            </div>
            <h3 class="fs-4">Simple Binary with Postfix</h3>
            <?php include "generated/highlighted-shiki/when-five-plus-five-equals-eleven/test2_left.html"; ?>
            <div class="test-fail">
                As a refresh, here's our original test case.
            </div>

            <h3 id="ir-dump" class="fw-bolder mb-4 mt-5">
                <a href="#ir-dump" class="text-reset text-decoration-none">Checking the IR</a>
            </h3>
            <p>
                With NCC, we have CLI options to dump the program representation at various stages of compilation.
                NCC uses a three address code intermediate representation called TACKY which follows the parsing and semantic analysis stages.
                Looking at the generated TACKY intermediate representation of our failed test case immediately revealed the problem:
            </p>

            <?php include "generated/highlighted-shiki/when-five-plus-five-equals-eleven/tacky_buggy.html"; ?>
            <p>
                The postfix increment was updating <code>i</code> to 6 <em>before</em> the addition operation used the first <code>i</code>.
                This meant the addition was using the already-incremented value.
            </p>
            
            <h2 id="the-solution" class="fw-bolder mb-4 mt-5">
                <a href="#the-solution" class="text-reset text-decoration-none">The Solution</a>
            </h2>
            
            <p>
                The fix was to capture the value of the left operand <em>before</em> evaluating the right operand. 
                In the TACKY generation for binary expressions:
            </p>
            
            <?php include "generated/highlighted-shiki/when-five-plus-five-equals-eleven/rust_solution.html"; ?>
            
            <p>
                This ensures that when <code>src1</code> is a variable, we capture its value in a temporary <em>before</em> evaluating <code>src2</code> 
                (which might have side effects that modify the variable).
            </p>
            
            <h2 id="the-result" class="fw-bolder mb-4 mt-5">
                <a href="#the-result" class="text-reset text-decoration-none">The Result</a>
            </h2>
            
            <p>After the fix, the TACKY IR for our previously failed test case becomes:</p>
            
            <?php include "generated/highlighted-shiki/when-five-plus-five-equals-eleven/tacky_fixed.html"; ?>
            
            <p>Now the addition uses the captured value (5) instead of the modified value (6).</p>
            <p>
                By ensuring strict left-to-right evaluation and properly capturing values before side effects can modify them, we've eliminated a class of undefined behavior from our compiler.
                This makes programs compiled with our compiler more predictable and easier to debug - a worthwhile tradeoff for the slight additional complexity in the compiler implementation.
            </p>

            <p>
                The fix was surprisingly simple once we understood the problem: just capture variable values in temporaries before evaluating expressions that might modify them.
                Sometimes the best solutions are the simplest ones!
            </p>

            <h3 id="other-undefined-behaviors" class="fw-bolder mb-4 mt-5">
                <a href="#other-undefined-behaviors" class="text-reset text-decoration-none">Other Undefined Behaviors</a>
            </h3>
            <p>
                There are a few other cases of undefined behavior in the subset of C that NCC implements so far.
            </p>
            <ul class="list-unstyled ps-3">
                <li class="mb-4 fs-5"><strong>Signed Integer Overflow</strong>
                    <p class="mb-2 mt-2 fs-6">
                        I've defined this to wrap around using two's complement arithmetic.
                        This is consistent with how most modern hardware behaves, and avoids the overhead of detecting overflow at runtime.
                        I'll likely add a flag to enable trapping on overflow in the future.
                    </p>
                </li>
                <li class="mb-4 fs-5"><strong>Uninitialized Variables</strong>
                    <p class="mb-2 mt-2 fs-6">
                        I haven't done anything special to handle this case yet, but we could pick a default value and initialize all variables at declaration.
                        This would come with a high performance cost (think arrays) for likely unneeded allocations, and I'm skeptical that zero is substantially better than garbage values.
                        Once again due to pointer arithmetic and aliasing, a static analysis approach like Rust's borrow checker isn't possible.
                    </p>
                </li>
                <li class="mb-4 fs-5"><strong>Divide By Zero</strong>
                    <p class="mb-2 mt-2 fs-6">
                        This would have to be another runtime check.
                    </p>
                </li>
            </ul>
            <p>
                That's just what I'm sure of off the top of my head.
                There are likely other lurking undefined behaviors; for example, what if you shift a 32-bit integer by 32 or more bits?
            </p>
            
            <h2 id="conclusion" class="fw-bolder mb-4 mt-5">
                <a href="#conclusion" class="text-reset text-decoration-none">Reflection</a>
            </h2>
            <p>
                Writing a C compiler (and writing about it) has so far proved to be an exceptional learning experience.
                Seeing the faults of C first-hand yields a much deeper understanding of the problems modern languages are trying to solve.
                Many of the lessons are surprisingly transferable to my career as a data scientist.
                Designing, manipulating, and pattern matching abstract syntax trees directly parallels working with decision trees, parsing nested JSON structures, and transforming data through pipeline stages.
                It's also quite helpful to have a strong understanding of what's happening under the hood when one needs to write performant code for handling big datasets.
            </p>
            <h3 class="fs-4">What's next</h3>
            <p>
                For NCC, <i>functions</i> are the next frontier - bringing challenges like stack management, calling conventions, and the question of whether to maintain strict left-to-right evaluation across function boundaries.
                With that NCC will be able to support basic standard library functions like <code>putchar</code>.
                <i>Storage-class specifiers</i> (<code>static</code>, <code>extern</code>) will come next which will finally let NCC compile programs with multiple translation units.
                Once that's all complete, I'll write about the experience and lessons learned from this project before moving onto implementing more complex types.
                Until then, keep on the lookout for more technical deep dives like this.
                I believe my recent struggles on implementing <code>switch</code> statements (now fully supported in NCC!) deserves its own post.
            </p>
        </section>
        <?php include("includes/say_hello.html") ?>
    </article>
</div>
<?php include "includes/footer.php"; ?>