/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./node_modules/@babel/runtime/node_modules/regenerator-runtime/runtime.js":
/*!*********************************************************************************!*\
  !*** ./node_modules/@babel/runtime/node_modules/regenerator-runtime/runtime.js ***!
  \*********************************************************************************/
/***/ ((module) => {

/**
 * Copyright (c) 2014-present, Facebook, Inc.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

var runtime = (function (exports) {
  "use strict";

  var Op = Object.prototype;
  var hasOwn = Op.hasOwnProperty;
  var undefined; // More compressible than void 0.
  var $Symbol = typeof Symbol === "function" ? Symbol : {};
  var iteratorSymbol = $Symbol.iterator || "@@iterator";
  var asyncIteratorSymbol = $Symbol.asyncIterator || "@@asyncIterator";
  var toStringTagSymbol = $Symbol.toStringTag || "@@toStringTag";

  function define(obj, key, value) {
    Object.defineProperty(obj, key, {
      value: value,
      enumerable: true,
      configurable: true,
      writable: true
    });
    return obj[key];
  }
  try {
    // IE 8 has a broken Object.defineProperty that only works on DOM objects.
    define({}, "");
  } catch (err) {
    define = function(obj, key, value) {
      return obj[key] = value;
    };
  }

  function wrap(innerFn, outerFn, self, tryLocsList) {
    // If outerFn provided and outerFn.prototype is a Generator, then outerFn.prototype instanceof Generator.
    var protoGenerator = outerFn && outerFn.prototype instanceof Generator ? outerFn : Generator;
    var generator = Object.create(protoGenerator.prototype);
    var context = new Context(tryLocsList || []);

    // The ._invoke method unifies the implementations of the .next,
    // .throw, and .return methods.
    generator._invoke = makeInvokeMethod(innerFn, self, context);

    return generator;
  }
  exports.wrap = wrap;

  // Try/catch helper to minimize deoptimizations. Returns a completion
  // record like context.tryEntries[i].completion. This interface could
  // have been (and was previously) designed to take a closure to be
  // invoked without arguments, but in all the cases we care about we
  // already have an existing method we want to call, so there's no need
  // to create a new function object. We can even get away with assuming
  // the method takes exactly one argument, since that happens to be true
  // in every case, so we don't have to touch the arguments object. The
  // only additional allocation required is the completion record, which
  // has a stable shape and so hopefully should be cheap to allocate.
  function tryCatch(fn, obj, arg) {
    try {
      return { type: "normal", arg: fn.call(obj, arg) };
    } catch (err) {
      return { type: "throw", arg: err };
    }
  }

  var GenStateSuspendedStart = "suspendedStart";
  var GenStateSuspendedYield = "suspendedYield";
  var GenStateExecuting = "executing";
  var GenStateCompleted = "completed";

  // Returning this object from the innerFn has the same effect as
  // breaking out of the dispatch switch statement.
  var ContinueSentinel = {};

  // Dummy constructor functions that we use as the .constructor and
  // .constructor.prototype properties for functions that return Generator
  // objects. For full spec compliance, you may wish to configure your
  // minifier not to mangle the names of these two functions.
  function Generator() {}
  function GeneratorFunction() {}
  function GeneratorFunctionPrototype() {}

  // This is a polyfill for %IteratorPrototype% for environments that
  // don't natively support it.
  var IteratorPrototype = {};
  define(IteratorPrototype, iteratorSymbol, function () {
    return this;
  });

  var getProto = Object.getPrototypeOf;
  var NativeIteratorPrototype = getProto && getProto(getProto(values([])));
  if (NativeIteratorPrototype &&
      NativeIteratorPrototype !== Op &&
      hasOwn.call(NativeIteratorPrototype, iteratorSymbol)) {
    // This environment has a native %IteratorPrototype%; use it instead
    // of the polyfill.
    IteratorPrototype = NativeIteratorPrototype;
  }

  var Gp = GeneratorFunctionPrototype.prototype =
    Generator.prototype = Object.create(IteratorPrototype);
  GeneratorFunction.prototype = GeneratorFunctionPrototype;
  define(Gp, "constructor", GeneratorFunctionPrototype);
  define(GeneratorFunctionPrototype, "constructor", GeneratorFunction);
  GeneratorFunction.displayName = define(
    GeneratorFunctionPrototype,
    toStringTagSymbol,
    "GeneratorFunction"
  );

  // Helper for defining the .next, .throw, and .return methods of the
  // Iterator interface in terms of a single ._invoke method.
  function defineIteratorMethods(prototype) {
    ["next", "throw", "return"].forEach(function(method) {
      define(prototype, method, function(arg) {
        return this._invoke(method, arg);
      });
    });
  }

  exports.isGeneratorFunction = function(genFun) {
    var ctor = typeof genFun === "function" && genFun.constructor;
    return ctor
      ? ctor === GeneratorFunction ||
        // For the native GeneratorFunction constructor, the best we can
        // do is to check its .name property.
        (ctor.displayName || ctor.name) === "GeneratorFunction"
      : false;
  };

  exports.mark = function(genFun) {
    if (Object.setPrototypeOf) {
      Object.setPrototypeOf(genFun, GeneratorFunctionPrototype);
    } else {
      genFun.__proto__ = GeneratorFunctionPrototype;
      define(genFun, toStringTagSymbol, "GeneratorFunction");
    }
    genFun.prototype = Object.create(Gp);
    return genFun;
  };

  // Within the body of any async function, `await x` is transformed to
  // `yield regeneratorRuntime.awrap(x)`, so that the runtime can test
  // `hasOwn.call(value, "__await")` to determine if the yielded value is
  // meant to be awaited.
  exports.awrap = function(arg) {
    return { __await: arg };
  };

  function AsyncIterator(generator, PromiseImpl) {
    function invoke(method, arg, resolve, reject) {
      var record = tryCatch(generator[method], generator, arg);
      if (record.type === "throw") {
        reject(record.arg);
      } else {
        var result = record.arg;
        var value = result.value;
        if (value &&
            typeof value === "object" &&
            hasOwn.call(value, "__await")) {
          return PromiseImpl.resolve(value.__await).then(function(value) {
            invoke("next", value, resolve, reject);
          }, function(err) {
            invoke("throw", err, resolve, reject);
          });
        }

        return PromiseImpl.resolve(value).then(function(unwrapped) {
          // When a yielded Promise is resolved, its final value becomes
          // the .value of the Promise<{value,done}> result for the
          // current iteration.
          result.value = unwrapped;
          resolve(result);
        }, function(error) {
          // If a rejected Promise was yielded, throw the rejection back
          // into the async generator function so it can be handled there.
          return invoke("throw", error, resolve, reject);
        });
      }
    }

    var previousPromise;

    function enqueue(method, arg) {
      function callInvokeWithMethodAndArg() {
        return new PromiseImpl(function(resolve, reject) {
          invoke(method, arg, resolve, reject);
        });
      }

      return previousPromise =
        // If enqueue has been called before, then we want to wait until
        // all previous Promises have been resolved before calling invoke,
        // so that results are always delivered in the correct order. If
        // enqueue has not been called before, then it is important to
        // call invoke immediately, without waiting on a callback to fire,
        // so that the async generator function has the opportunity to do
        // any necessary setup in a predictable way. This predictability
        // is why the Promise constructor synchronously invokes its
        // executor callback, and why async functions synchronously
        // execute code before the first await. Since we implement simple
        // async functions in terms of async generators, it is especially
        // important to get this right, even though it requires care.
        previousPromise ? previousPromise.then(
          callInvokeWithMethodAndArg,
          // Avoid propagating failures to Promises returned by later
          // invocations of the iterator.
          callInvokeWithMethodAndArg
        ) : callInvokeWithMethodAndArg();
    }

    // Define the unified helper method that is used to implement .next,
    // .throw, and .return (see defineIteratorMethods).
    this._invoke = enqueue;
  }

  defineIteratorMethods(AsyncIterator.prototype);
  define(AsyncIterator.prototype, asyncIteratorSymbol, function () {
    return this;
  });
  exports.AsyncIterator = AsyncIterator;

  // Note that simple async functions are implemented on top of
  // AsyncIterator objects; they just return a Promise for the value of
  // the final result produced by the iterator.
  exports.async = function(innerFn, outerFn, self, tryLocsList, PromiseImpl) {
    if (PromiseImpl === void 0) PromiseImpl = Promise;

    var iter = new AsyncIterator(
      wrap(innerFn, outerFn, self, tryLocsList),
      PromiseImpl
    );

    return exports.isGeneratorFunction(outerFn)
      ? iter // If outerFn is a generator, return the full iterator.
      : iter.next().then(function(result) {
          return result.done ? result.value : iter.next();
        });
  };

  function makeInvokeMethod(innerFn, self, context) {
    var state = GenStateSuspendedStart;

    return function invoke(method, arg) {
      if (state === GenStateExecuting) {
        throw new Error("Generator is already running");
      }

      if (state === GenStateCompleted) {
        if (method === "throw") {
          throw arg;
        }

        // Be forgiving, per 25.3.3.3.3 of the spec:
        // https://people.mozilla.org/~jorendorff/es6-draft.html#sec-generatorresume
        return doneResult();
      }

      context.method = method;
      context.arg = arg;

      while (true) {
        var delegate = context.delegate;
        if (delegate) {
          var delegateResult = maybeInvokeDelegate(delegate, context);
          if (delegateResult) {
            if (delegateResult === ContinueSentinel) continue;
            return delegateResult;
          }
        }

        if (context.method === "next") {
          // Setting context._sent for legacy support of Babel's
          // function.sent implementation.
          context.sent = context._sent = context.arg;

        } else if (context.method === "throw") {
          if (state === GenStateSuspendedStart) {
            state = GenStateCompleted;
            throw context.arg;
          }

          context.dispatchException(context.arg);

        } else if (context.method === "return") {
          context.abrupt("return", context.arg);
        }

        state = GenStateExecuting;

        var record = tryCatch(innerFn, self, context);
        if (record.type === "normal") {
          // If an exception is thrown from innerFn, we leave state ===
          // GenStateExecuting and loop back for another invocation.
          state = context.done
            ? GenStateCompleted
            : GenStateSuspendedYield;

          if (record.arg === ContinueSentinel) {
            continue;
          }

          return {
            value: record.arg,
            done: context.done
          };

        } else if (record.type === "throw") {
          state = GenStateCompleted;
          // Dispatch the exception by looping back around to the
          // context.dispatchException(context.arg) call above.
          context.method = "throw";
          context.arg = record.arg;
        }
      }
    };
  }

  // Call delegate.iterator[context.method](context.arg) and handle the
  // result, either by returning a { value, done } result from the
  // delegate iterator, or by modifying context.method and context.arg,
  // setting context.delegate to null, and returning the ContinueSentinel.
  function maybeInvokeDelegate(delegate, context) {
    var method = delegate.iterator[context.method];
    if (method === undefined) {
      // A .throw or .return when the delegate iterator has no .throw
      // method always terminates the yield* loop.
      context.delegate = null;

      if (context.method === "throw") {
        // Note: ["return"] must be used for ES3 parsing compatibility.
        if (delegate.iterator["return"]) {
          // If the delegate iterator has a return method, give it a
          // chance to clean up.
          context.method = "return";
          context.arg = undefined;
          maybeInvokeDelegate(delegate, context);

          if (context.method === "throw") {
            // If maybeInvokeDelegate(context) changed context.method from
            // "return" to "throw", let that override the TypeError below.
            return ContinueSentinel;
          }
        }

        context.method = "throw";
        context.arg = new TypeError(
          "The iterator does not provide a 'throw' method");
      }

      return ContinueSentinel;
    }

    var record = tryCatch(method, delegate.iterator, context.arg);

    if (record.type === "throw") {
      context.method = "throw";
      context.arg = record.arg;
      context.delegate = null;
      return ContinueSentinel;
    }

    var info = record.arg;

    if (! info) {
      context.method = "throw";
      context.arg = new TypeError("iterator result is not an object");
      context.delegate = null;
      return ContinueSentinel;
    }

    if (info.done) {
      // Assign the result of the finished delegate to the temporary
      // variable specified by delegate.resultName (see delegateYield).
      context[delegate.resultName] = info.value;

      // Resume execution at the desired location (see delegateYield).
      context.next = delegate.nextLoc;

      // If context.method was "throw" but the delegate handled the
      // exception, let the outer generator proceed normally. If
      // context.method was "next", forget context.arg since it has been
      // "consumed" by the delegate iterator. If context.method was
      // "return", allow the original .return call to continue in the
      // outer generator.
      if (context.method !== "return") {
        context.method = "next";
        context.arg = undefined;
      }

    } else {
      // Re-yield the result returned by the delegate method.
      return info;
    }

    // The delegate iterator is finished, so forget it and continue with
    // the outer generator.
    context.delegate = null;
    return ContinueSentinel;
  }

  // Define Generator.prototype.{next,throw,return} in terms of the
  // unified ._invoke helper method.
  defineIteratorMethods(Gp);

  define(Gp, toStringTagSymbol, "Generator");

  // A Generator should always return itself as the iterator object when the
  // @@iterator function is called on it. Some browsers' implementations of the
  // iterator prototype chain incorrectly implement this, causing the Generator
  // object to not be returned from this call. This ensures that doesn't happen.
  // See https://github.com/facebook/regenerator/issues/274 for more details.
  define(Gp, iteratorSymbol, function() {
    return this;
  });

  define(Gp, "toString", function() {
    return "[object Generator]";
  });

  function pushTryEntry(locs) {
    var entry = { tryLoc: locs[0] };

    if (1 in locs) {
      entry.catchLoc = locs[1];
    }

    if (2 in locs) {
      entry.finallyLoc = locs[2];
      entry.afterLoc = locs[3];
    }

    this.tryEntries.push(entry);
  }

  function resetTryEntry(entry) {
    var record = entry.completion || {};
    record.type = "normal";
    delete record.arg;
    entry.completion = record;
  }

  function Context(tryLocsList) {
    // The root entry object (effectively a try statement without a catch
    // or a finally block) gives us a place to store values thrown from
    // locations where there is no enclosing try statement.
    this.tryEntries = [{ tryLoc: "root" }];
    tryLocsList.forEach(pushTryEntry, this);
    this.reset(true);
  }

  exports.keys = function(object) {
    var keys = [];
    for (var key in object) {
      keys.push(key);
    }
    keys.reverse();

    // Rather than returning an object with a next method, we keep
    // things simple and return the next function itself.
    return function next() {
      while (keys.length) {
        var key = keys.pop();
        if (key in object) {
          next.value = key;
          next.done = false;
          return next;
        }
      }

      // To avoid creating an additional object, we just hang the .value
      // and .done properties off the next function object itself. This
      // also ensures that the minifier will not anonymize the function.
      next.done = true;
      return next;
    };
  };

  function values(iterable) {
    if (iterable) {
      var iteratorMethod = iterable[iteratorSymbol];
      if (iteratorMethod) {
        return iteratorMethod.call(iterable);
      }

      if (typeof iterable.next === "function") {
        return iterable;
      }

      if (!isNaN(iterable.length)) {
        var i = -1, next = function next() {
          while (++i < iterable.length) {
            if (hasOwn.call(iterable, i)) {
              next.value = iterable[i];
              next.done = false;
              return next;
            }
          }

          next.value = undefined;
          next.done = true;

          return next;
        };

        return next.next = next;
      }
    }

    // Return an iterator with no values.
    return { next: doneResult };
  }
  exports.values = values;

  function doneResult() {
    return { value: undefined, done: true };
  }

  Context.prototype = {
    constructor: Context,

    reset: function(skipTempReset) {
      this.prev = 0;
      this.next = 0;
      // Resetting context._sent for legacy support of Babel's
      // function.sent implementation.
      this.sent = this._sent = undefined;
      this.done = false;
      this.delegate = null;

      this.method = "next";
      this.arg = undefined;

      this.tryEntries.forEach(resetTryEntry);

      if (!skipTempReset) {
        for (var name in this) {
          // Not sure about the optimal order of these conditions:
          if (name.charAt(0) === "t" &&
              hasOwn.call(this, name) &&
              !isNaN(+name.slice(1))) {
            this[name] = undefined;
          }
        }
      }
    },

    stop: function() {
      this.done = true;

      var rootEntry = this.tryEntries[0];
      var rootRecord = rootEntry.completion;
      if (rootRecord.type === "throw") {
        throw rootRecord.arg;
      }

      return this.rval;
    },

    dispatchException: function(exception) {
      if (this.done) {
        throw exception;
      }

      var context = this;
      function handle(loc, caught) {
        record.type = "throw";
        record.arg = exception;
        context.next = loc;

        if (caught) {
          // If the dispatched exception was caught by a catch block,
          // then let that catch block handle the exception normally.
          context.method = "next";
          context.arg = undefined;
        }

        return !! caught;
      }

      for (var i = this.tryEntries.length - 1; i >= 0; --i) {
        var entry = this.tryEntries[i];
        var record = entry.completion;

        if (entry.tryLoc === "root") {
          // Exception thrown outside of any try block that could handle
          // it, so set the completion value of the entire function to
          // throw the exception.
          return handle("end");
        }

        if (entry.tryLoc <= this.prev) {
          var hasCatch = hasOwn.call(entry, "catchLoc");
          var hasFinally = hasOwn.call(entry, "finallyLoc");

          if (hasCatch && hasFinally) {
            if (this.prev < entry.catchLoc) {
              return handle(entry.catchLoc, true);
            } else if (this.prev < entry.finallyLoc) {
              return handle(entry.finallyLoc);
            }

          } else if (hasCatch) {
            if (this.prev < entry.catchLoc) {
              return handle(entry.catchLoc, true);
            }

          } else if (hasFinally) {
            if (this.prev < entry.finallyLoc) {
              return handle(entry.finallyLoc);
            }

          } else {
            throw new Error("try statement without catch or finally");
          }
        }
      }
    },

    abrupt: function(type, arg) {
      for (var i = this.tryEntries.length - 1; i >= 0; --i) {
        var entry = this.tryEntries[i];
        if (entry.tryLoc <= this.prev &&
            hasOwn.call(entry, "finallyLoc") &&
            this.prev < entry.finallyLoc) {
          var finallyEntry = entry;
          break;
        }
      }

      if (finallyEntry &&
          (type === "break" ||
           type === "continue") &&
          finallyEntry.tryLoc <= arg &&
          arg <= finallyEntry.finallyLoc) {
        // Ignore the finally entry if control is not jumping to a
        // location outside the try/catch block.
        finallyEntry = null;
      }

      var record = finallyEntry ? finallyEntry.completion : {};
      record.type = type;
      record.arg = arg;

      if (finallyEntry) {
        this.method = "next";
        this.next = finallyEntry.finallyLoc;
        return ContinueSentinel;
      }

      return this.complete(record);
    },

    complete: function(record, afterLoc) {
      if (record.type === "throw") {
        throw record.arg;
      }

      if (record.type === "break" ||
          record.type === "continue") {
        this.next = record.arg;
      } else if (record.type === "return") {
        this.rval = this.arg = record.arg;
        this.method = "return";
        this.next = "end";
      } else if (record.type === "normal" && afterLoc) {
        this.next = afterLoc;
      }

      return ContinueSentinel;
    },

    finish: function(finallyLoc) {
      for (var i = this.tryEntries.length - 1; i >= 0; --i) {
        var entry = this.tryEntries[i];
        if (entry.finallyLoc === finallyLoc) {
          this.complete(entry.completion, entry.afterLoc);
          resetTryEntry(entry);
          return ContinueSentinel;
        }
      }
    },

    "catch": function(tryLoc) {
      for (var i = this.tryEntries.length - 1; i >= 0; --i) {
        var entry = this.tryEntries[i];
        if (entry.tryLoc === tryLoc) {
          var record = entry.completion;
          if (record.type === "throw") {
            var thrown = record.arg;
            resetTryEntry(entry);
          }
          return thrown;
        }
      }

      // The context.catch method must only be called with a location
      // argument that corresponds to a known catch block.
      throw new Error("illegal catch attempt");
    },

    delegateYield: function(iterable, resultName, nextLoc) {
      this.delegate = {
        iterator: values(iterable),
        resultName: resultName,
        nextLoc: nextLoc
      };

      if (this.method === "next") {
        // Deliberately forget the last sent value so that we don't
        // accidentally pass it on to the delegate.
        this.arg = undefined;
      }

      return ContinueSentinel;
    }
  };

  // Regardless of whether this script is executing as a CommonJS module
  // or not, return the runtime object so that we can declare the variable
  // regeneratorRuntime in the outer scope, which allows this module to be
  // injected easily by `bin/regenerator --include-runtime script.js`.
  return exports;

}(
  // If this script is executing as a CommonJS module, use module.exports
  // as the regeneratorRuntime namespace. Otherwise create a new empty
  // object. Either way, the resulting object will be used to initialize
  // the regeneratorRuntime variable at the top of this file.
   true ? module.exports : 0
));

try {
  regeneratorRuntime = runtime;
} catch (accidentalStrictMode) {
  // This module should not be running in strict mode, so the above
  // assignment should always work unless something is misconfigured. Just
  // in case runtime.js accidentally runs in strict mode, in modern engines
  // we can explicitly access globalThis. In older engines we can escape
  // strict mode using a global Function call. This could conceivably fail
  // if a Content Security Policy forbids using Function, but in that case
  // the proper solution is to fix the accidental strict mode problem. If
  // you've misconfigured your bundler to force strict mode and applied a
  // CSP to forbid Function, and you're not willing to fix either of those
  // problems, please detail your unique predicament in a GitHub issue.
  if (typeof globalThis === "object") {
    globalThis.regeneratorRuntime = runtime;
  } else {
    Function("r", "regeneratorRuntime = r")(runtime);
  }
}


/***/ }),

/***/ "./node_modules/@babel/runtime/regenerator/index.js":
/*!**********************************************************!*\
  !*** ./node_modules/@babel/runtime/regenerator/index.js ***!
  \**********************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

module.exports = __webpack_require__(/*! regenerator-runtime */ "./node_modules/@babel/runtime/node_modules/regenerator-runtime/runtime.js");


/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be in strict mode.
(() => {
"use strict";
/*!**************************************!*\
  !*** ./assets/src/admin/js/admin.js ***!
  \**************************************/
/* harmony import */ var _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/regenerator */ "./node_modules/@babel/runtime/regenerator/index.js");
/* harmony import */ var _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0__);


function asyncGeneratorStep(gen, resolve, reject, _next, _throw, key, arg) { try { var info = gen[key](arg); var value = info.value; } catch (error) { reject(error); return; } if (info.done) { resolve(value); } else { Promise.resolve(value).then(_next, _throw); } }

function _asyncToGenerator(fn) { return function () { var self = this, args = arguments; return new Promise(function (resolve, reject) { var gen = fn.apply(self, args); function _next(value) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "next", value); } function _throw(err) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "throw", err); } _next(undefined); }); }; }

(function ($) {
  var $notice_container = $(".rufous-admin-big-notice--container");
  var selectedFrontPage = 0;

  if (!window.rufous_admin) {
    return;
  }

  var _window$rufous_admin = window.rufous_admin,
      builderStatusData = _window$rufous_admin.builderStatusData,
      getStartedData = _window$rufous_admin.getStartedData;
  $notice_container.on("click", ".predefined-front-pages li", function (event) {
    var $item = $(event.currentTarget);
    $item.addClass("selected");
    $item.siblings().removeClass("selected");
  });

  function disableNotice() {
    wp.ajax.post("colibriwp_disable_big_notice", {
      nonce: builderStatusData.kubio_disable_big_notice_nonce
    });
  }

  function toggleProcessing(value) {
    $(window).on("beforeunload.rufous-admin-big-notice", function () {
      return true;
    });

    if (value) {
      $(".rufous-admin-big-notice").addClass("processing");
      $(".rufous-admin-big-notice .action-buttons").fadeOut();
    } else {
      $(".rufous-admin-big-notice").removeClass("processing");
    }
  }

  function showOverlay(message) {
    var $overlay = jQuery(".colibri-customizer-overlay");

    if (!$overlay.length) {
      $overlay = jQuery("" + '<div class="colibri-customizer-overlay">\n' + '        <div class="colibri-customizer-overlay-content">\n' + '            <span class="colibri-customizer-overlay-loader"></span>\n' + '            <span class="colibri-customizer-overlay-message"></span>\n' + "        </div>\n" + "    </div>");
      jQuery("body").append($overlay);
    }

    $(".colibri-customizer-overlay-message").html(message);
    $overlay.fadeIn();
  }

  function hideOverlay() {
    var $overlay = jQuery(".colibri-customizer-overlay");
    $overlay.fadeOut();
  }

  function pluginNotice(message) {
    $notice_container.find(".plugin-notice .message").html(message);
    $notice_container.find(".plugin-notice").fadeIn();
    showOverlay(message);
  }

  function installBuilder(callback) {
    pluginNotice(builderStatusData.messages.installing);
    $.get(builderStatusData.install_url).done(function () {
      toggleProcessing(true);
      activateBuilder(callback);
    }).always(function () {
      $(window).off("beforeunload.rufous-admin-big-notice");
    });
  }

  function activateBuilder(callback) {
    pluginNotice(builderStatusData.messages.activating);
    wp.ajax.post(getStartedData.theme_prefix + "activate_plugin", {
      slug: builderStatusData.slug,
      _wpnonce: builderStatusData.plugin_activate_nonce
    }).done(function (response) {
      setTimeout(function () {
        $(window).off("beforeunload.rufous-admin-big-notice");
        window.location = response.redirect || window.location;
      }, 500);
    });
  }
  /**
   * Siteleads start
   */


  function sleep(_x) {
    return _sleep.apply(this, arguments);
  }

  function _sleep() {
    _sleep = _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee(time) {
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee$(_context) {
        while (1) {
          switch (_context.prev = _context.next) {
            case 0:
              return _context.abrupt("return", new Promise(function (resolve) {
                return setTimeout(resolve, time);
              }));

            case 1:
            case "end":
              return _context.stop();
          }
        }
      }, _callee);
    }));
    return _sleep.apply(this, arguments);
  }

  function saveCustomizerSettings() {
    return _saveCustomizerSettings.apply(this, arguments);
  }

  function _saveCustomizerSettings() {
    _saveCustomizerSettings = _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee2() {
      var promiseResolve, promise, doneCallback, executeCallback;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee2$(_context2) {
        while (1) {
          switch (_context2.prev = _context2.next) {
            case 0:
              promise = new Promise(function (resolve, reject) {
                promiseResolve = resolve;
              });

              doneCallback = function doneCallback() {
                promiseResolve();
              };

              try {
                if (!_.isEmpty(wp.customize.dirtyValues())) {
                  executeCallback = true;
                  wp.customize.bind('save', function () {
                    if (executeCallback) {
                      $(window).off('beforeunload');
                      setTimeout(doneCallback, 2000);
                      executeCallback = false;
                    }
                  });
                  wp.customize.previewer.save();
                } else {
                  $(window).off('beforeunload');
                  setTimeout(doneCallback, 500);
                }
              } catch (e) {
                doneCallback();
                console.error(e);
              }

              _context2.next = 5;
              return promise;

            case 5:
            case "end":
              return _context2.stop();
          }
        }
      }, _callee2);
    }));
    return _saveCustomizerSettings.apply(this, arguments);
  }

  function prepareSiteLeadsPlugin() {
    return _prepareSiteLeadsPlugin.apply(this, arguments);
  }
  /**
   * Siteleads end
   */


  function _prepareSiteLeadsPlugin() {
    _prepareSiteLeadsPlugin = _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee9() {
      var globalDataObject, getSiteLeadsBackendData, siteLeadsIntegrationIsEnabled, getTranslatedText, PLUGIN_STATUSES, requestIsPending, currentStatus, onHandleButtonClick, _onHandleButtonClick, installAndActivateSiteLeads, _installAndActivateSiteLeads, onInstallSiteLeadsPlugin, _onInstallSiteLeadsPlugin, onActivateSiteLeads, _onActivateSiteLeads, initSetupForSiteLeadsPlugin, _initSetupForSiteLeadsPlugin;

      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee9$(_context9) {
        while (1) {
          switch (_context9.prev = _context9.next) {
            case 0:
              _initSetupForSiteLeadsPlugin = function _initSetupForSiteLead2() {
                _initSetupForSiteLeadsPlugin = _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee8() {
                  var ajaxHandle, startSource, nonce, promise;
                  return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee8$(_context8) {
                    while (1) {
                      switch (_context8.prev = _context8.next) {
                        case 0:
                          ajaxHandle = getSiteLeadsBackendData('siteLeadsInitWpAjaxHandle');
                          startSource = getSiteLeadsBackendData('startSource');
                          nonce = getSiteLeadsBackendData('siteLeadsNonce');
                          promise = new Promise(function (resolve, reject) {
                            wp.ajax.post(ajaxHandle, {
                              _wpnonce: nonce,
                              'start_source': startSource
                            }).done(function (response) {
                              resolve(response);
                            }).fail(function (error) {
                              reject(error);
                            });
                          });
                          _context8.prev = 4;
                          pluginNotice(getTranslatedText('initSetupSiteLeads'));
                          _context8.next = 8;
                          return promise;

                        case 8:
                          _context8.next = 10;
                          return sleep(100);

                        case 10:
                          _context8.next = 16;
                          break;

                        case 12:
                          _context8.prev = 12;
                          _context8.t0 = _context8["catch"](4);
                          console.error(_context8.t0);
                          return _context8.abrupt("return", false);

                        case 16:
                        case "end":
                          return _context8.stop();
                      }
                    }
                  }, _callee8, null, [[4, 12]]);
                }));
                return _initSetupForSiteLeadsPlugin.apply(this, arguments);
              };

              initSetupForSiteLeadsPlugin = function _initSetupForSiteLead() {
                return _initSetupForSiteLeadsPlugin.apply(this, arguments);
              };

              _onActivateSiteLeads = function _onActivateSiteLeads3() {
                _onActivateSiteLeads = _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee7() {
                  var activationUrl, promise;
                  return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee7$(_context7) {
                    while (1) {
                      switch (_context7.prev = _context7.next) {
                        case 0:
                          activationUrl = getSiteLeadsBackendData('activationLink');
                          promise = new Promise( /*#__PURE__*/function () {
                            var _ref = _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee6(resolve, reject) {
                              var result;
                              return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee6$(_context6) {
                                while (1) {
                                  switch (_context6.prev = _context6.next) {
                                    case 0:
                                      _context6.prev = 0;
                                      _context6.next = 3;
                                      return fetch(activationUrl);

                                    case 3:
                                      result = _context6.sent;

                                      if (!(result !== null && result !== void 0 && result.ok)) {
                                        reject(result === null || result === void 0 ? void 0 : result.statusText);
                                      }

                                      resolve();
                                      _context6.next = 11;
                                      break;

                                    case 8:
                                      _context6.prev = 8;
                                      _context6.t0 = _context6["catch"](0);
                                      reject(_context6.t0);

                                    case 11:
                                    case "end":
                                      return _context6.stop();
                                  }
                                }
                              }, _callee6, null, [[0, 8]]);
                            }));

                            return function (_x3, _x4) {
                              return _ref.apply(this, arguments);
                            };
                          }());
                          _context7.prev = 2;
                          pluginNotice(getTranslatedText('activatingSiteLeads'));
                          _context7.next = 6;
                          return promise;

                        case 6:
                          _context7.next = 8;
                          return sleep(100);

                        case 8:
                          _context7.next = 10;
                          return saveCustomizerSettings();

                        case 10:
                          _context7.next = 12;
                          return initSetupForSiteLeadsPlugin();

                        case 12:
                          _context7.next = 14;
                          return sleep(100);

                        case 14:
                          return _context7.abrupt("return", true);

                        case 17:
                          _context7.prev = 17;
                          _context7.t0 = _context7["catch"](2);
                          console.error(_context7.t0);
                          return _context7.abrupt("return", false);

                        case 21:
                        case "end":
                          return _context7.stop();
                      }
                    }
                  }, _callee7, null, [[2, 17]]);
                }));
                return _onActivateSiteLeads.apply(this, arguments);
              };

              onActivateSiteLeads = function _onActivateSiteLeads2() {
                return _onActivateSiteLeads.apply(this, arguments);
              };

              _onInstallSiteLeadsPlugin = function _onInstallSiteLeadsPl2() {
                _onInstallSiteLeadsPlugin = _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee5() {
                  var slug, promise;
                  return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee5$(_context5) {
                    while (1) {
                      switch (_context5.prev = _context5.next) {
                        case 0:
                          slug = getSiteLeadsBackendData('pluginSlug');
                          promise = new Promise(function (resolve, reject) {
                            wp.updates.ajax("install-plugin", {
                              slug: slug,
                              success: function success() {
                                resolve();
                              },
                              error: function error(e) {
                                if ('folder_exists' === e.errorCode) {
                                  resolve();
                                } else {
                                  reject();
                                }
                              }
                            });
                          });
                          _context5.prev = 2;
                          pluginNotice(getTranslatedText('installingSiteLeads'));
                          _context5.next = 6;
                          return promise;

                        case 6:
                          _context5.next = 8;
                          return sleep(100);

                        case 8:
                          return _context5.abrupt("return", true);

                        case 11:
                          _context5.prev = 11;
                          _context5.t0 = _context5["catch"](2);
                          console.error(_context5.t0);
                          return _context5.abrupt("return", false);

                        case 15:
                        case "end":
                          return _context5.stop();
                      }
                    }
                  }, _callee5, null, [[2, 11]]);
                }));
                return _onInstallSiteLeadsPlugin.apply(this, arguments);
              };

              onInstallSiteLeadsPlugin = function _onInstallSiteLeadsPl() {
                return _onInstallSiteLeadsPlugin.apply(this, arguments);
              };

              _installAndActivateSiteLeads = function _installAndActivateSi2() {
                _installAndActivateSiteLeads = _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee4() {
                  var installResponse, activateResponse;
                  return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee4$(_context4) {
                    while (1) {
                      switch (_context4.prev = _context4.next) {
                        case 0:
                          _context4.prev = 0;
                          _context4.next = 3;
                          return onInstallSiteLeadsPlugin();

                        case 3:
                          installResponse = _context4.sent;

                          if (installResponse) {
                            _context4.next = 6;
                            break;
                          }

                          return _context4.abrupt("return", false);

                        case 6:
                          _context4.next = 8;
                          return onActivateSiteLeads();

                        case 8:
                          activateResponse = _context4.sent;

                          if (activateResponse) {
                            _context4.next = 11;
                            break;
                          }

                          return _context4.abrupt("return", false);

                        case 11:
                          return _context4.abrupt("return", true);

                        case 14:
                          _context4.prev = 14;
                          _context4.t0 = _context4["catch"](0);
                          console.error(_context4.t0);
                          return _context4.abrupt("return", false);

                        case 18:
                        case "end":
                          return _context4.stop();
                      }
                    }
                  }, _callee4, null, [[0, 14]]);
                }));
                return _installAndActivateSiteLeads.apply(this, arguments);
              };

              installAndActivateSiteLeads = function _installAndActivateSi() {
                return _installAndActivateSiteLeads.apply(this, arguments);
              };

              _onHandleButtonClick = function _onHandleButtonClick3() {
                _onHandleButtonClick = _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee3() {
                  return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee3$(_context3) {
                    while (1) {
                      switch (_context3.prev = _context3.next) {
                        case 0:
                          if (!(currentStatus === PLUGIN_STATUSES.ACTIVE)) {
                            _context3.next = 2;
                            break;
                          }

                          return _context3.abrupt("return");

                        case 2:
                          if (!requestIsPending) {
                            _context3.next = 4;
                            break;
                          }

                          return _context3.abrupt("return");

                        case 4:
                          requestIsPending = true;
                          _context3.t0 = currentStatus;
                          _context3.next = _context3.t0 === PLUGIN_STATUSES.NOT_INSTALLED ? 8 : _context3.t0 === PLUGIN_STATUSES.INSTALLED ? 11 : 14;
                          break;

                        case 8:
                          _context3.next = 10;
                          return installAndActivateSiteLeads();

                        case 10:
                          return _context3.abrupt("break", 14);

                        case 11:
                          _context3.next = 13;
                          return onActivateSiteLeads();

                        case 13:
                          return _context3.abrupt("break", 14);

                        case 14:
                          requestIsPending = false;

                        case 15:
                        case "end":
                          return _context3.stop();
                      }
                    }
                  }, _callee3);
                }));
                return _onHandleButtonClick.apply(this, arguments);
              };

              onHandleButtonClick = function _onHandleButtonClick2() {
                return _onHandleButtonClick.apply(this, arguments);
              };

              getTranslatedText = function _getTranslatedText(name) {
                return getSiteLeadsBackendData(['translations', name], name);
              };

              getSiteLeadsBackendData = function _getSiteLeadsBackendD(path, defaultValue) {
                return _.get(globalDataObject === null || globalDataObject === void 0 ? void 0 : globalDataObject.siteLeads, path, defaultValue);
              };

              globalDataObject = window.rufous_admin; //same for the other file

              siteLeadsIntegrationIsEnabled = getSiteLeadsBackendData('siteLeadsIntegrationIsEnabled');

              if (siteLeadsIntegrationIsEnabled) {
                _context9.next = 16;
                break;
              }

              return _context9.abrupt("return");

            case 16:
              PLUGIN_STATUSES = {
                NOT_INSTALLED: 'not-installed',
                INSTALLED: 'installed',
                ACTIVE: 'active'
              };
              requestIsPending = false;
              currentStatus = getSiteLeadsBackendData('pluginStatus');
              ;
              ;
              ;
              ;
              ;
              _context9.next = 26;
              return onHandleButtonClick();

            case 26:
            case "end":
              return _context9.stop();
          }
        }
      }, _callee9);
    }));
    return _prepareSiteLeadsPlugin.apply(this, arguments);
  }

  function processBuilderInstalationStepts(_x2) {
    return _processBuilderInstalationStepts.apply(this, arguments);
  }

  function _processBuilderInstalationStepts() {
    _processBuilderInstalationStepts = _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee10(callback) {
      var _ref2,
          _ref2$AI,
          AI,
          _ref2$source,
          source,
          _args10 = arguments;

      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee10$(_context10) {
        while (1) {
          switch (_context10.prev = _context10.next) {
            case 0:
              _ref2 = _args10.length > 1 && _args10[1] !== undefined ? _args10[1] : {}, _ref2$AI = _ref2.AI, AI = _ref2$AI === void 0 ? false : _ref2$AI, _ref2$source = _ref2.source, source = _ref2$source === void 0 ? "notice" : _ref2$source;
              _context10.prev = 1;
              _context10.next = 4;
              return prepareSiteLeadsPlugin();

            case 4:
              _context10.next = 9;
              break;

            case 6:
              _context10.prev = 6;
              _context10.t0 = _context10["catch"](1);
              console.error(_context10.t0);

            case 9:
              pluginNotice(builderStatusData.messages.preparing);
              wp.ajax.post(getStartedData.theme_prefix + "front_set_predesign", {
                index: selectedFrontPage,
                AI: AI ? "yes" : "no",
                nonce: builderStatusData.kubio_front_set_predesign_nonce,
                source: source
              }).done(function () {
                if (builderStatusData.status === "not-installed") {
                  toggleProcessing(true);
                  installBuilder(callback);
                }

                if (builderStatusData.status === "installed") {
                  toggleProcessing(true);
                  activateBuilder(callback);
                }
              });

            case 11:
            case "end":
              return _context10.stop();
          }
        }
      }, _callee10, null, [[1, 6]]);
    }));
    return _processBuilderInstalationStepts.apply(this, arguments);
  }

  $notice_container.on("click", ".start-with-predefined-design-button", function () {
    selectedFrontPage = $(".selected[data-index]").data("index");
    processBuilderInstalationStepts(function () {}, {
      AI: selectedFrontPage === 4
    });
  });
  $notice_container.on("click", ".start-with-ai-page", function () {
    selectedFrontPage = $(".selected[data-index]").data("index");
    processBuilderInstalationStepts(function () {}, {
      AI: true
    });
  });
  $notice_container.on("click", ".view-all-demos", function () {
    selectedFrontPage = 0;
    processBuilderInstalationStepts(function () {}, {
      AI: false,
      source: "starter-sites"
    });
  });
  $notice_root = $notice_container.closest(".rufous-admin-big-notice");
  $custom_close_button = $notice_root.find(".rufous-notice-dont-show-container");

  if ($custom_close_button.length) {
    $custom_close_button.on("click", function () {
      disableNotice();
      $notice_container.closest(".rufous-admin-big-notice").find("button.notice-dismiss").click();
    });
  } else {
    $notice_root.on("click", ".notice-dismiss", disableNotice);
  }

  var $document = $(document);

  var colibriInstallPluginSuccess = function colibriInstallPluginSuccess(response) {
    var $message = $(".plugin-card-" + response.slug).find(".install-now");
    $message.removeClass("updating-message").addClass("updated-message installed button-disabled").attr("aria-label", wp.updates.l10n.pluginInstalledLabel.replace("%s", response.pluginName)).text(wp.updates.l10n.pluginInstalled);
    wp.a11y.speak(wp.updates.l10n.installedMsg, "polite");
    $document.trigger("wp-plugin-install-success", response);

    if (response.activateUrl) {
      // Transform the 'Install' button into an 'Activate' button.
      $message.removeClass("install-now installed button-disabled updated-message").addClass("activate-now").attr("href", response.activateUrl).attr("aria-label", wp.updates.l10n.activatePluginLabel.replace("%s", response.pluginName)).text(wp.updates.l10n.activatePlugin);
      $message.click();
    }
  };

  var colibriInstallPlugin = function colibriInstallPlugin(event) {
    var $button = $(event.target);
    event.preventDefault();

    if ($button.hasClass("updating-message") || $button.hasClass("button-disabled")) {
      return;
    }

    if (wp.updates.shouldRequestFilesystemCredentials && !wp.updates.ajaxLocked) {
      wp.updates.requestFilesystemCredentials(event);
      $document.on("credential-modal-cancel", function () {
        var $message = $(".install-now.updating-message");
        $message.removeClass("updating-message").text(wp.updates.l10n.installNow);
        wp.a11y.speak(wp.updates.l10n.updateCancel, "polite");
      });
    }

    wp.updates.installPlugin({
      slug: $button.data("slug"),
      success: colibriInstallPluginSuccess
    });
  };

  var colibriActivatePlugin = function colibriActivatePlugin(event) {
    var $button = $(event.target);
    event.preventDefault();
    $button.addClass("updating-message").removeClass("active-plugin").text(getStartedData.activating);
    jQuery.get(this.href).done(function (data) {
      $button.text(getStartedData.plugin_installed_and_active);
      wp.a11y.speak(getStartedData.plugin_installed_and_active, "polite");
    }).fail(function (error) {
      $button.text(getStartedData.activate);
    }).always(function () {
      $button.removeClass("updating-message").addClass("active-plugin");
    });
  }; // $document.on("click", ".install-now", colibriInstallPlugin);
  // $document.on("click", ".activate-now", colibriActivatePlugin);


  $(document).ready(function () {
    if (getStartedData !== null && getStartedData !== void 0 && getStartedData.install_recommended) {
      $(".plugin-card-" + getStartedData.install_recommended + " a.button").trigger("click");
    }
  });
  window.rufous_admin.showOverlay = showOverlay;
})(jQuery);
})();

/******/ })()
;
