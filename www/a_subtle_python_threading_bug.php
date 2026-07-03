<?php
require_once "includes/classes.php";

$page_info = PageInfo::fromDB('a_subtle_python_threading_bug');

include_once "includes/top.php";
?>
    <div class="container blog-post pb-2">
        <article>
            <?php $page_info->renderFullHeader(); ?>
            <div class="mb-5">
                <p>
                    I recently encountered a puzzling bug in code that looked something like this:
                </p>
                <?php include "generated/highlighted-shiki/a_subtle_python_threading_bug/threading_example.html"; ?>
                <p>
                    This is a pretty common pattern for handling embarrassingly parallel problems in Python.
                    We have a worker function (in this case sum) that takes a chunk of our data, processes it, and returns a result.
                    My real use case involved loading a large number of small parquet files from cloud storage.
                    This is an IO bound problem making it a good candidate for threading.
                    Unfortunately, the above code has a fatal flaw, can you spot it?
                </p>
                <button data-action="reveal" class="btn btn-reveal-answer my-2">
                    Click to reveal the answer
                </button>
                <p class="hidden-answer p-3 mt-2">
                    I'll give you a hint, all the talk about threading is irrelevant.<br>
                    The issue is how python handles closures, like the lambda function in the example.
                </p>
            </div>
            <section>
                <h2 id="closures-in-python" class="fw-bolder mb-4 mt-5">
                    <a href="#closures-in-python" class="text-reset text-decoration-none">Closures in Python</a>
                </h2>
                <p class="lead shadow-sm py-2 ps-2 rounded-3">
                    <b class="fs-5">Closure:</b> A closure is a function object that has access to variables in its lexical scope, even after the scope has finished executing.
                </p>
                <p>
                    In the code above, the lambda function is a closure because it captures <code>batch</code>. Here <code>batch</code> is being captured by reference, not by value. This means all threads end up using the same <code>batch</code> value—the last one from the loop. Beyond lambdas, Python creates closures whenever you have nested functions, generator expressions, or decorator functions that access variables from their enclosing scope.
                </p>
                <p class="lead shadow-sm py-2 ps-2 rounded-3">
                    <b class="fs-5">Variable Capturing:</b> The process by which a closure gains access to and retains references to variables from its enclosing scope, allowing it to use those variables even after the enclosing scope has finished executing.
                </p>
                <p>
                    Python creates closures using a mechanism called "cell objects".
                    When a nested function references a variable from an enclosing scope, Python creates a cell to hold a reference to that variable.
                    The closure stores these cells in its <code>__closure__</code> attribute. You can inspect this by calling <code>func.__closure__</code> on any closure function.
                    Each cell points to the actual variable object, not a copy of its value at creation time.
                    This is why all lambdas in a loop often end up with the same value—they're all pointing to the same cell, which references the loop variable after it has finished iterating.
                </p>
                <p>
                    This behavior differs from languages like Rust or Go, where loop variables are typically captured by value. JavaScript has similar late binding but uses block scope with <code>let</code> to create a new binding for each iteration.
                    Python's approach is consistent with its general philosophy of variables as name bindings rather than storage locations, but it can surprise developers coming from other languages.
                </p>
                <h3 id="minimal-example" class="fw-bolder mb-4 mt-5">
                    <a href="#minimal-example" class="text-reset text-decoration-none">A Minimal Example</a>
                </h3>
                Now that we're all up to speed on closures, let's break this problem down to its simplest form.
                We use a lambda in a list comprehension to initialize <code>x</code>, a list of functions.
                On the following line we use a list comprehension to evaluate each function in <code>x</code> and collect the results in a new list.
                <?php include "generated/highlighted-shiki/a_subtle_python_threading_bug/minimal_example_broken.html"; ?>
                <p>
                    As you can see the output is <code>[4, 4, 4, 4, 4]</code>, not <code>[0, 1, 2, 3, 4]</code> as you might expect.
                </p>
                <p>
                    To get the expected output, we must capture the value at lambda creation:
                </p>
                <?php include "generated/highlighted-shiki/a_subtle_python_threading_bug/minimal_example_fixed.html"; ?>
                <p>
                    A subtle change, but very different behavior.
                    In this snippet a default argument is used to capture the current value of <code>i</code> at each iteration.
                    Default arguments are evaluated when the function is defined so a new object <code>i</code> that shadows the outer <code>i</code> is created for each lambda.
                    This effectively captures the value by creating a new binding in the lambda's local scope.
                </p>
            </section>
            <section>
                <h2 id="late-binding" class="fw-bolder mb-4 mt-5">
                    <a href="#late-binding" class="text-reset text-decoration-none">Late Binding in Python</a>
                </h2>
                <p>
                    This behavior is called <b>late binding</b>.
                    Python uses late binding as it simplifies the language design with simple uniform semantics.
                    In Python, names are always resolved at runtime and objects are created when their definitions are executed.
                    This design makes closures lightweight, but can lead to subtle bugs if you're not careful.
                </p>
            </section>
            <section>
                <h2 id="contrast-with-other-languages" class="fw-bolder mb-4 mt-5">
                    <a href="#contrast-with-other-languages" class="text-reset text-decoration-none">Contrast with Other Languages</a>
                </h2>
                <p>
                    Most compiled languages use <b>early binding</b> by default, capturing the value at the time the closure is created.
                    This avoids the bug shown above, enables (often significant) compiler optimizations, and reduces runtime errors via compiler checks.
                </p>
                <p>
                    Some argue that late binding offers more expressiveness, allowing programmers to write more flexible and dynamic code.
                    I'm personally unconvinced; intuitive code is good code.
                    The same expressiveness can be achieved with early binding through explicit captures or higher-order functions, without the footgun of unexpected variable sharing.
                    The "flexibility" of late binding is really just ambiguity that leads to bugs like the one we've seen, along with missed optimization opportunities that compiled languages can leverage.
                </p>
            </section>
            <section>
                <h2 id="takeaway" class="fw-bolder mb-4 mt-5">
                    <a href="#takeaway" class="text-reset text-decoration-none">Takeaway</a>
                </h2>
                <p>
                    Understanding subtle details of how your programming language works is crucial for writing correct code.
                    Python's late binding behavior is consistent and predictable once you know about it, but it violates many programmers' intuitions.
                    When creating closures in loops, always be explicit about what values you want to capture.
                    This can be achieved with default arguments, factory functions, or <code>functools.partial</code>.

                    Remember clear and explicit is good, future readers of your code (statistically likely to be you) will thank you.
                </p>
            </section>
            <?php include("includes/say_hello.html") ?>
        </article>
    </div>
<?php include "includes/footer.php"; ?>