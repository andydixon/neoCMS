/*
 * jQuery UI 1.7.2
 *
 * Copyright (c) 2009 AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT (MIT-LICENSE.txt)
 * and GPL (GPL-LICENSE.txt) licenses.
 *
 * http://docs.jquery.com/UI
 */
jQuery.ui || (function (c) {
    var i = c.fn.remove, d = c.browser.mozilla && (parseFloat(c.browser.version) < 1.9);
    c.ui = {
        version: "1.7.2",
        plugin: {
            add: function (k, l, n) {
                var m = c.ui[k].prototype;
                for (var j in n) {
                    m.plugins[j] = m.plugins[j] || [];
                    m.plugins[j].push([l, n[j]])
                }
            }, call: function (j, l, k) {
                var n = j.plugins[l];
                if (!n || !j.element[0].parentNode) {
                    return
                }
                for (var m = 0; m < n.length; m++) {
                    if (j.options[n[m][0]]) {
                        n[m][1].apply(j.element, k)
                    }
                }
            }
        },
        contains: function (k, j) {
            return document.compareDocumentPosition ? k.compareDocumentPosition(j) & 16 : k !== j && k.contains(j)
        },
        hasScroll: function (m, k) {
            if (c(m).css("overflow") == "hidden") {
                return false
            }
            var j = (k && k == "left") ? "scrollLeft" : "scrollTop", l = false;
            if (m[j] > 0) {
                return true
            }
            m[j] = 1;
            l = (m[j] > 0);
            m[j] = 0;
            return l
        },
        isOverAxis: function (k, j, l) {
            return (k > j) && (k < (j + l))
        },
        isOver: function (o, k, n, m, j, l) {
            return c.ui.isOverAxis(o, n, j) && c.ui.isOverAxis(k, m, l)
        },
        keyCode: {
            BACKSPACE: 8,
            CAPS_LOCK: 20,
            COMMA: 188,
            CONTROL: 17,
            DELETE: 46,
            DOWN: 40,
            END: 35,
            ENTER: 13,
            ESCAPE: 27,
            HOME: 36,
            INSERT: 45,
            LEFT: 37,
            NUMPAD_ADD: 107,
            NUMPAD_DECIMAL: 110,
            NUMPAD_DIVIDE: 111,
            NUMPAD_ENTER: 108,
            NUMPAD_MULTIPLY: 106,
            NUMPAD_SUBTRACT: 109,
            PAGE_DOWN: 34,
            PAGE_UP: 33,
            PERIOD: 190,
            RIGHT: 39,
            SHIFT: 16,
            SPACE: 32,
            TAB: 9,
            UP: 38
        }
    };
    if (d) {
        var f = c.attr, e = c.fn.removeAttr, h = "http://www.w3.org/2005/07/aaa", a = /^aria-/, b = /^wairole:/;
        c.attr = function (k, j, l) {
            var m = l !== undefined;
            return (j == "role" ? (m ? f.call(this, k, j, "wairole:" + l) : (f.apply(this, arguments) || "").replace(b, "")) : (a.test(j) ? (m ? k.setAttributeNS(h, j.replace(a, "aaa:"), l) : f.call(this, k, j.replace(a, "aaa:"))) : f.apply(this, arguments)))
        };
        c.fn.removeAttr = function (j) {
            return (a.test(j) ? this.each(function () {
                this.removeAttributeNS(h, j.replace(a, ""))
            }) : e.call(this, j))
        }
    }
    c.fn.extend({
        remove: function () {
            c("*", this).add(this).each(function () {
                c(this).triggerHandler("remove")
            });
            return i.apply(this, arguments)
        }, enableSelection: function () {
            return this.attr("unselectable", "off").css("MozUserSelect", "").unbind("selectstart.ui")
        }, disableSelection: function () {
            return this.attr("unselectable", "on").css("MozUserSelect", "none").bind("selectstart.ui", function () {
                return false
            })
        }, scrollParent: function () {
            var j;
            if ((c.browser.msie && (/(static|relative)/).test(this.css("position"))) || (/absolute/).test(this.css("position"))) {
                j = this.parents().filter(function () {
                    return (/(relative|absolute|fixed)/).test(c.curCSS(this, "position", 1)) && (/(auto|scroll)/).test(c.curCSS(this, "overflow", 1) + c.curCSS(this, "overflow-y", 1) + c.curCSS(this, "overflow-x", 1))
                }).eq(0)
            } else {
                j = this.parents().filter(function () {
                    return (/(auto|scroll)/).test(c.curCSS(this, "overflow", 1) + c.curCSS(this, "overflow-y", 1) + c.curCSS(this, "overflow-x", 1))
                }).eq(0)
            }
            return (/fixed/).test(this.css("position")) || !j.length ? c(document) : j
        }
    });
    c.extend(c.expr[":"], {
        data: function (l, k, j) {
            return !!c.data(l, j[3])
        }, focusable: function (k) {
            var l = k.nodeName.toLowerCase(), j = c.attr(k, "tabindex");
            return (/input|select|textarea|button|object/.test(l) ? !k.disabled : "a" == l || "area" == l ? k.href || !isNaN(j) : !isNaN(j)) && !c(k)["area" == l ? "parents" : "closest"](":hidden").length
        }, tabbable: function (k) {
            var j = c.attr(k, "tabindex");
            return (isNaN(j) || j >= 0) && c(k).is(":focusable")
        }
    });

    function g(m, n, o, l) {
        function k(q) {
            var p = c[m][n][q] || [];
            return (typeof p == "string" ? p.split(/,?\s+/) : p)
        }

        var j = k("getter");
        if (l.length == 1 && typeof l[0] == "string") {
            j = j.concat(k("getterSetter"))
        }
        return (c.inArray(o, j) != -1)
    }

    c.widget = function (k, j) {
        var l = k.split(".")[0];
        k = k.split(".")[1];
        c.fn[k] = function (p) {
            var n = (typeof p == "string"), o = Array.prototype.slice.call(arguments, 1);
            if (n && p.substring(0, 1) == "_") {
                return this
            }
            if (n && g(l, k, p, o)) {
                var m = c.data(this[0], k);
                return (m ? m[p].apply(m, o) : undefined)
            }
            return this.each(function () {
                var q = c.data(this, k);
                (!q && !n && c.data(this, k, new c[l][k](this, p))._init());
                (q && n && c.isFunction(q[p]) && q[p].apply(q, o))
            })
        };
        c[l] = c[l] || {};
        c[l][k] = function (o, n) {
            var m = this;
            this.namespace = l;
            this.widgetName = k;
            this.widgetEventPrefix = c[l][k].eventPrefix || k;
            this.widgetBaseClass = l + "-" + k;
            this.options = c.extend({}, c.widget.defaults, c[l][k].defaults, c.metadata && c.metadata.get(o)[k], n);
            this.element = c(o).bind("setData." + k, function (q, p, r) {
                if (q.target == o) {
                    return m._setData(p, r)
                }
            }).bind("getData." + k, function (q, p) {
                if (q.target == o) {
                    return m._getData(p)
                }
            }).bind("remove", function () {
                return m.destroy()
            })
        };
        c[l][k].prototype = c.extend({}, c.widget.prototype, j);
        c[l][k].getterSetter = "option"
    };
    c.widget.prototype = {
        _init: function () {
        }, destroy: function () {
            this.element.removeData(this.widgetName).removeClass(this.widgetBaseClass + "-disabled " + this.namespace + "-state-disabled").removeAttr("aria-disabled")
        }, option: function (l, m) {
            var k = l, j = this;
            if (typeof l == "string") {
                if (m === undefined) {
                    return this._getData(l)
                }
                k = {};
                k[l] = m
            }
            c.each(k, function (n, o) {
                j._setData(n, o)
            })
        }, _getData: function (j) {
            return this.options[j]
        }, _setData: function (j, k) {
            this.options[j] = k;
            if (j == "disabled") {
                this.element[k ? "addClass" : "removeClass"](this.widgetBaseClass + "-disabled " + this.namespace + "-state-disabled").attr("aria-disabled", k)
            }
        }, enable: function () {
            this._setData("disabled", false)
        }, disable: function () {
            this._setData("disabled", true)
        }, _trigger: function (l, m, n) {
            var p = this.options[l], j = (l == this.widgetEventPrefix ? l : this.widgetEventPrefix + l);
            m = c.Event(m);
            m.type = j;
            if (m.originalEvent) {
                for (var k = c.event.props.length, o; k;) {
                    o = c.event.props[--k];
                    m[o] = m.originalEvent[o]
                }
            }
            this.element.trigger(m, n);
            return !(c.isFunction(p) && p.call(this.element[0], m, n) === false || m.isDefaultPrevented())
        }
    };
    c.widget.defaults = {disabled: false};
    c.ui.mouse = {
        _mouseInit: function () {
            var j = this;
            this.element.bind("mousedown." + this.widgetName, function (k) {
                return j._mouseDown(k)
            }).bind("click." + this.widgetName, function (k) {
                if (j._preventClickEvent) {
                    j._preventClickEvent = false;
                    k.stopImmediatePropagation();
                    return false
                }
            });
            if (c.browser.msie) {
                this._mouseUnselectable = this.element.attr("unselectable");
                this.element.attr("unselectable", "on")
            }
            this.started = false
        }, _mouseDestroy: function () {
            this.element.unbind("." + this.widgetName);
            (c.browser.msie && this.element.attr("unselectable", this._mouseUnselectable))
        }, _mouseDown: function (l) {
            l.originalEvent = l.originalEvent || {};
            if (l.originalEvent.mouseHandled) {
                return
            }
            (this._mouseStarted && this._mouseUp(l));
            this._mouseDownEvent = l;
            var k = this, m = (l.which == 1),
                j = (typeof this.options.cancel == "string" ? c(l.target).parents().add(l.target).filter(this.options.cancel).length : false);
            if (!m || j || !this._mouseCapture(l)) {
                return true
            }
            this.mouseDelayMet = !this.options.delay;
            if (!this.mouseDelayMet) {
                this._mouseDelayTimer = setTimeout(function () {
                    k.mouseDelayMet = true
                }, this.options.delay)
            }
            if (this._mouseDistanceMet(l) && this._mouseDelayMet(l)) {
                this._mouseStarted = (this._mouseStart(l) !== false);
                if (!this._mouseStarted) {
                    l.preventDefault();
                    return true
                }
            }
            this._mouseMoveDelegate = function (n) {
                return k._mouseMove(n)
            };
            this._mouseUpDelegate = function (n) {
                return k._mouseUp(n)
            };
            c(doc(this)).bind("mousemove." + this.widgetName, this._mouseMoveDelegate).bind("mouseup." + this.widgetName, this._mouseUpDelegate);
            l.preventDefault();
            l.originalEvent.mouseHandled = true;
            return true
        }, _mouseMove: function (j) {
            if (c.browser.msie && !j.button) {
                return this._mouseUp(j)
            }
            if (this._mouseStarted) {
                this._mouseDrag(j);
                return j.preventDefault()
            }
            if (this._mouseDistanceMet(j) && this._mouseDelayMet(j)) {
                this._mouseStarted = (this._mouseStart(this._mouseDownEvent, j) !== false);
                (this._mouseStarted ? this._mouseDrag(j) : this._mouseUp(j))
            }
            return !this._mouseStarted
        }, _mouseUp: function (j) {
            c(doc(this)).unbind("mousemove." + this.widgetName, this._mouseMoveDelegate).unbind("mouseup." + this.widgetName, this._mouseUpDelegate);
            if (this._mouseStarted) {
                this._mouseStarted = false;
                this._preventClickEvent = (j.target == this._mouseDownEvent.target);
                this._mouseStop(j)
            }
            return false
        }, _mouseDistanceMet: function (j) {
            return (Math.max(Math.abs(this._mouseDownEvent.pageX - j.pageX), Math.abs(this._mouseDownEvent.pageY - j.pageY)) >= this.options.distance)
        }, _mouseDelayMet: function (j) {
            return this.mouseDelayMet
        }, _mouseStart: function (j) {
        }, _mouseDrag: function (j) {
        }, _mouseStop: function (j) {
        }, _mouseCapture: function (j) {
            return true
        }
    };
    c.ui.mouse.defaults = {cancel: null, distance: 1, delay: 0}
})(jQuery);
;/*
 * jQuery UI Draggable 1.7.2
 *
 * Copyright (c) 2009 AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT (MIT-LICENSE.txt)
 * and GPL (GPL-LICENSE.txt) licenses.
 *
 * http://docs.jquery.com/UI/Draggables
 *
 * Depends:
 *	ui.core.js
 */
(function (a) {
    a.widget("ui.draggable", a.extend({}, a.ui.mouse, {
        _init: function () {
            if (this.options.helper == "original" && !(/^(?:r|a|f)/).test(this.element.css("position"))) {
                this.element[0].style.position = "relative"
            }
            (this.options.addClasses && this.element.addClass("ui-draggable"));
            (this.options.disabled && this.element.addClass("ui-draggable-disabled"));
            this._mouseInit()
        }, destroy: function () {
            if (!this.element.data("draggable")) {
                return
            }
            this.element.removeData("draggable").unbind(".draggable").removeClass("ui-draggable ui-draggable-dragging ui-draggable-disabled");
            this._mouseDestroy()
        }, _mouseCapture: function (b) {
            var c = this.options;
            if (this.helper || c.disabled || a(b.target).is(".ui-resizable-handle")) {
                return false
            }
            this.handle = this._getHandle(b);
            if (!this.handle) {
                return false
            }
            return true
        }, _mouseStart: function (b) {
            var c = this.options;
            this.helper = this._createHelper(b);
            this._cacheHelperProportions();
            if (a.ui.ddmanager) {
                a.ui.ddmanager.current = this
            }
            this._cacheMargins();
            this.cssPosition = this.helper.css("position");
            this.scrollParent = this.helper.scrollParent();
            this.offset = this.element.offset();
            this.offset = {top: this.offset.top - this.margins.top, left: this.offset.left - this.margins.left};
            a.extend(this.offset, {
                click: {left: b.pageX - this.offset.left, top: b.pageY - this.offset.top},
                parent: this._getParentOffset(),
                relative: this._getRelativeOffset()
            });
            this.originalPosition = this._generatePosition(b);
            this.originalPageX = b.pageX;
            this.originalPageY = b.pageY;
            if (c.cursorAt) {
                this._adjustOffsetFromHelper(c.cursorAt)
            }
            if (c.containment) {
                this._setContainment()
            }
            this._trigger("start", b);
            this._cacheHelperProportions();
            if (a.ui.ddmanager && !c.dropBehaviour) {
                a.ui.ddmanager.prepareOffsets(this, b)
            }
            this.helper.addClass("ui-draggable-dragging");
            this._mouseDrag(b, true);
            return true
        }, _mouseDrag: function (b, d) {
            this.position = this._generatePosition(b);
            this.positionAbs = this._convertPositionTo("absolute");
            if (!d) {
                var c = this._uiHash();
                this._trigger("drag", b, c);
                this.position = c.position
            }
            if (!this.options.axis || this.options.axis != "y") {
                this.helper[0].style.left = this.position.left + "px"
            }
            if (!this.options.axis || this.options.axis != "x") {
                this.helper[0].style.top = this.position.top + "px"
            }
            if (a.ui.ddmanager) {
                a.ui.ddmanager.drag(this, b)
            }
            return false
        }, _mouseStop: function (c) {
            var d = false;
            if (a.ui.ddmanager && !this.options.dropBehaviour) {
                d = a.ui.ddmanager.drop(this, c)
            }
            if (this.dropped) {
                d = this.dropped;
                this.dropped = false
            }
            if ((this.options.revert == "invalid" && !d) || (this.options.revert == "valid" && d) || this.options.revert === true || (a.isFunction(this.options.revert) && this.options.revert.call(this.element, d))) {
                var b = this;
                a(this.helper).animate(this.originalPosition, parseInt(this.options.revertDuration, 10), function () {
                    b._trigger("stop", c);
                    b._clear()
                })
            } else {
                this._trigger("stop", c);
                this._clear()
            }
            return false
        }, _getHandle: function (b) {
            var c = !this.options.handle || !a(this.options.handle, this.element).length ? true : false;
            a(this.options.handle, this.element).find("*").andSelf().each(function () {
                if (this == b.target) {
                    c = true
                }
            });
            return c
        }, _createHelper: function (c) {
            var d = this.options;
            var b = a.isFunction(d.helper) ? a(d.helper.apply(this.element[0], [c])) : (d.helper == "clone" ? this.element.clone() : this.element);
            if (!b.parents("body").length) {
                b.appendTo((d.appendTo == "parent" ? this.element[0].parentNode : d.appendTo))
            }
            if (b[0] != this.element[0] && !(/(fixed|absolute)/).test(b.css("position"))) {
                b.css("position", "absolute")
            }
            return b
        }, _adjustOffsetFromHelper: function (b) {
            if (b.left != undefined) {
                this.offset.click.left = b.left + this.margins.left
            }
            if (b.right != undefined) {
                this.offset.click.left = this.helperProportions.width - b.right + this.margins.left
            }
            if (b.top != undefined) {
                this.offset.click.top = b.top + this.margins.top
            }
            if (b.bottom != undefined) {
                this.offset.click.top = this.helperProportions.height - b.bottom + this.margins.top
            }
        }, _getParentOffset: function () {
            this.offsetParent = this.helper.offsetParent();
            var b = this.offsetParent.offset();
            if (this.cssPosition == "absolute" && this.scrollParent[0] != document && a.ui.contains(this.scrollParent[0], this.offsetParent[0])) {
                b.left += this.scrollParent.scrollLeft();
                b.top += this.scrollParent.scrollTop()
            }
            if ((this.offsetParent[0] == doc(this).body) || (this.offsetParent[0].tagName && this.offsetParent[0].tagName.toLowerCase() == "html" && a.browser.msie)) {
                b = {top: 0, left: 0}
            }
            return {
                top: b.top + (parseInt(this.offsetParent.css("borderTopWidth"), 10) || 0),
                left: b.left + (parseInt(this.offsetParent.css("borderLeftWidth"), 10) || 0)
            }
        }, _getRelativeOffset: function () {
            if (this.cssPosition == "relative") {
                var b = this.element.position();
                return {
                    top: b.top - (parseInt(this.helper.css("top"), 10) || 0) + this.scrollParent.scrollTop(),
                    left: b.left - (parseInt(this.helper.css("left"), 10) || 0) + this.scrollParent.scrollLeft()
                }
            } else {
                return {top: 0, left: 0}
            }
        }, _cacheMargins: function () {
            this.margins = {
                left: (parseInt(this.element.css("marginLeft"), 10) || 0),
                top: (parseInt(this.element.css("marginTop"), 10) || 0)
            }
        }, _cacheHelperProportions: function () {
            this.helperProportions = {width: this.helper.outerWidth(), height: this.helper.outerHeight()}
        }, _setContainment: function () {
            var e = this.options;
            if (e.containment == "parent") {
                e.containment = this.helper[0].parentNode
            }
            if (e.containment == "document" || e.containment == "window") {
                this.containment = [0 - this.offset.relative.left - this.offset.parent.left, 0 - this.offset.relative.top - this.offset.parent.top, a(e.containment == "document" ? document : window).width() - this.helperProportions.width - this.margins.left, (a(e.containment == "document" ? document : window).height() || doc(this).body.parentNode.scrollHeight) - this.helperProportions.height - this.margins.top]
            }
            if (!(/^(document|window|parent)$/).test(e.containment) && e.containment.constructor != Array) {
                var c = a(e.containment)[0];
                if (!c) {
                    return
                }
                var d = a(e.containment).offset();
                var b = (a(c).css("overflow") != "hidden");
                this.containment = [d.left + (parseInt(a(c).css("borderLeftWidth"), 10) || 0) + (parseInt(a(c).css("paddingLeft"), 10) || 0) - this.margins.left, d.top + (parseInt(a(c).css("borderTopWidth"), 10) || 0) + (parseInt(a(c).css("paddingTop"), 10) || 0) - this.margins.top, d.left + (b ? Math.max(c.scrollWidth, c.offsetWidth) : c.offsetWidth) - (parseInt(a(c).css("borderLeftWidth"), 10) || 0) - (parseInt(a(c).css("paddingRight"), 10) || 0) - this.helperProportions.width - this.margins.left, d.top + (b ? Math.max(c.scrollHeight, c.offsetHeight) : c.offsetHeight) - (parseInt(a(c).css("borderTopWidth"), 10) || 0) - (parseInt(a(c).css("paddingBottom"), 10) || 0) - this.helperProportions.height - this.margins.top]
            } else {
                if (e.containment.constructor == Array) {
                    this.containment = e.containment
                }
            }
        }, _convertPositionTo: function (f, h) {
            if (!h) {
                h = this.position
            }
            var c = f == "absolute" ? 1 : -1;
            var e = this.options,
                b = this.cssPosition == "absolute" && !(this.scrollParent[0] != document && a.ui.contains(this.scrollParent[0], this.offsetParent[0])) ? this.offsetParent : this.scrollParent,
                g = (/(html|body)/i).test(b[0].tagName);
            return {
                top: (h.top + this.offset.relative.top * c + this.offset.parent.top * c - (a.browser.safari && this.cssPosition == "fixed" ? 0 : (this.cssPosition == "fixed" ? -this.scrollParent.scrollTop() : (g ? 0 : b.scrollTop())) * c)),
                left: (h.left + this.offset.relative.left * c + this.offset.parent.left * c - (a.browser.safari && this.cssPosition == "fixed" ? 0 : (this.cssPosition == "fixed" ? -this.scrollParent.scrollLeft() : g ? 0 : b.scrollLeft()) * c))
            }
        }, _generatePosition: function (e) {
            var h = this.options,
                b = this.cssPosition == "absolute" && !(this.scrollParent[0] != document && a.ui.contains(this.scrollParent[0], this.offsetParent[0])) ? this.offsetParent : this.scrollParent,
                i = (/(html|body)/i).test(b[0].tagName);
            if (this.cssPosition == "relative" && !(this.scrollParent[0] != document && this.scrollParent[0] != this.offsetParent[0])) {
                this.offset.relative = this._getRelativeOffset()
            }
            var d = e.pageX;
            var c = e.pageY;
            if (this.originalPosition) {
                if (this.containment) {
                    if (e.pageX - this.offset.click.left < this.containment[0]) {
                        d = this.containment[0] + this.offset.click.left
                    }
                    if (e.pageY - this.offset.click.top < this.containment[1]) {
                        c = this.containment[1] + this.offset.click.top
                    }
                    if (e.pageX - this.offset.click.left > this.containment[2]) {
                        d = this.containment[2] + this.offset.click.left
                    }
                    if (e.pageY - this.offset.click.top > this.containment[3]) {
                        c = this.containment[3] + this.offset.click.top
                    }
                }
                if (h.grid) {
                    var g = this.originalPageY + Math.round((c - this.originalPageY) / h.grid[1]) * h.grid[1];
                    c = this.containment ? (!(g - this.offset.click.top < this.containment[1] || g - this.offset.click.top > this.containment[3]) ? g : (!(g - this.offset.click.top < this.containment[1]) ? g - h.grid[1] : g + h.grid[1])) : g;
                    var f = this.originalPageX + Math.round((d - this.originalPageX) / h.grid[0]) * h.grid[0];
                    d = this.containment ? (!(f - this.offset.click.left < this.containment[0] || f - this.offset.click.left > this.containment[2]) ? f : (!(f - this.offset.click.left < this.containment[0]) ? f - h.grid[0] : f + h.grid[0])) : f
                }
            }
            return {
                top: (c - this.offset.click.top - this.offset.relative.top - this.offset.parent.top + (a.browser.safari && this.cssPosition == "fixed" ? 0 : (this.cssPosition == "fixed" ? -this.scrollParent.scrollTop() : (i ? 0 : b.scrollTop())))),
                left: (d - this.offset.click.left - this.offset.relative.left - this.offset.parent.left + (a.browser.safari && this.cssPosition == "fixed" ? 0 : (this.cssPosition == "fixed" ? -this.scrollParent.scrollLeft() : i ? 0 : b.scrollLeft())))
            }
        }, _clear: function () {
            this.helper.removeClass("ui-draggable-dragging");
            if (this.helper[0] != this.element[0] && !this.cancelHelperRemoval) {
                this.helper.remove()
            }
            this.helper = null;
            this.cancelHelperRemoval = false
        }, _trigger: function (b, c, d) {
            d = d || this._uiHash();
            a.ui.plugin.call(this, b, [c, d]);
            if (b == "drag") {
                this.positionAbs = this._convertPositionTo("absolute")
            }
            return a.widget.prototype._trigger.call(this, b, c, d)
        }, plugins: {}, _uiHash: function (b) {
            return {
                helper: this.helper,
                position: this.position,
                absolutePosition: this.positionAbs,
                offset: this.positionAbs
            }
        }
    }));
    a.extend(a.ui.draggable, {
        version: "1.7.2",
        eventPrefix: "drag",
        defaults: {
            addClasses: true,
            appendTo: "parent",
            axis: false,
            cancel: ":input,option",
            connectToSortable: false,
            containment: false,
            cursor: "auto",
            cursorAt: false,
            delay: 0,
            distance: 1,
            grid: false,
            handle: false,
            helper: "original",
            iframeFix: false,
            opacity: false,
            refreshPositions: false,
            revert: false,
            revertDuration: 500,
            scope: "default",
            scroll: true,
            scrollSensitivity: 20,
            scrollSpeed: 20,
            snap: false,
            snapMode: "both",
            snapTolerance: 20,
            stack: false,
            zIndex: false
        }
    });
    a.ui.plugin.add("draggable", "connectToSortable", {
        start: function (c, e) {
            var d = a(this).data("draggable"), f = d.options, b = a.extend({}, e, {item: d.element});
            d.sortables = [];
            a(f.connectToSortable).each(function () {
                var g = a.data(this, "sortable");
                if (g && !g.options.disabled) {
                    d.sortables.push({instance: g, shouldRevert: g.options.revert});
                    g._refreshItems();
                    g._trigger("activate", c, b)
                }
            })
        }, stop: function (c, e) {
            var d = a(this).data("draggable"), b = a.extend({}, e, {item: d.element});
            a.each(d.sortables, function () {
                if (this.instance.isOver) {
                    this.instance.isOver = 0;
                    d.cancelHelperRemoval = true;
                    this.instance.cancelHelperRemoval = false;
                    if (this.shouldRevert) {
                        this.instance.options.revert = true
                    }
                    this.instance._mouseStop(c);
                    this.instance.options.helper = this.instance.options._helper;
                    if (d.options.helper == "original") {
                        this.instance.currentItem.css({top: "auto", left: "auto"})
                    }
                } else {
                    this.instance.cancelHelperRemoval = false;
                    this.instance._trigger("deactivate", c, b)
                }
            })
        }, drag: function (c, f) {
            var e = a(this).data("draggable"), b = this;
            var d = function (i) {
                var n = this.offset.click.top, m = this.offset.click.left;
                var g = this.positionAbs.top, k = this.positionAbs.left;
                var j = i.height, l = i.width;
                var p = i.top, h = i.left;
                return a.ui.isOver(g + n, k + m, p, h, j, l)
            };
            a.each(e.sortables, function (g) {
                this.instance.positionAbs = e.positionAbs;
                this.instance.helperProportions = e.helperProportions;
                this.instance.offset.click = e.offset.click;
                if (this.instance._intersectsWith(this.instance.containerCache)) {
                    if (!this.instance.isOver) {
                        this.instance.isOver = 1;
                        this.instance.currentItem = a(b).clone().appendTo(this.instance.element).data("sortable-item", true);
                        this.instance.options._helper = this.instance.options.helper;
                        this.instance.options.helper = function () {
                            return f.helper[0]
                        };
                        c.target = this.instance.currentItem[0];
                        this.instance._mouseCapture(c, true);
                        this.instance._mouseStart(c, true, true);
                        this.instance.offset.click.top = e.offset.click.top;
                        this.instance.offset.click.left = e.offset.click.left;
                        this.instance.offset.parent.left -= e.offset.parent.left - this.instance.offset.parent.left;
                        this.instance.offset.parent.top -= e.offset.parent.top - this.instance.offset.parent.top;
                        e._trigger("toSortable", c);
                        e.dropped = this.instance.element;
                        e.currentItem = e.element;
                        this.instance.fromOutside = e
                    }
                    if (this.instance.currentItem) {
                        this.instance._mouseDrag(c)
                    }
                } else {
                    if (this.instance.isOver) {
                        this.instance.isOver = 0;
                        this.instance.cancelHelperRemoval = true;
                        this.instance.options.revert = false;
                        this.instance._trigger("out", c, this.instance._uiHash(this.instance));
                        this.instance._mouseStop(c, true);
                        this.instance.options.helper = this.instance.options._helper;
                        this.instance.currentItem.remove();
                        if (this.instance.placeholder) {
                            this.instance.placeholder.remove()
                        }
                        e._trigger("fromSortable", c);
                        e.dropped = false
                    }
                }
            })
        }
    });
    a.ui.plugin.add("draggable", "cursor", {
        start: function (c, d) {
            var b = a("body"), e = a(this).data("draggable").options;
            if (b.css("cursor")) {
                e._cursor = b.css("cursor")
            }
            b.css("cursor", e.cursor)
        }, stop: function (b, c) {
            var d = a(this).data("draggable").options;
            if (d._cursor) {
                a("body").css("cursor", d._cursor)
            }
        }
    });
    a.ui.plugin.add("draggable", "iframeFix", {
        start: function (b, c) {
            var d = a(this).data("draggable").options;
            a(d.iframeFix === true ? "iframe" : d.iframeFix).each(function () {
                a('<div class="ui-draggable-iframeFix" style="background: #fff;"></div>').css({
                    width: this.offsetWidth + "px",
                    height: this.offsetHeight + "px",
                    position: "absolute",
                    opacity: "0.001",
                    zIndex: 1000
                }).css(a(this).offset()).appendTo("body")
            })
        }, stop: function (b, c) {
            a("div.ui-draggable-iframeFix").each(function () {
                this.parentNode.removeChild(this)
            })
        }
    });
    a.ui.plugin.add("draggable", "opacity", {
        start: function (c, d) {
            var b = a(d.helper), e = a(this).data("draggable").options;
            if (b.css("opacity")) {
                e._opacity = b.css("opacity")
            }
            b.css("opacity", e.opacity)
        }, stop: function (b, c) {
            var d = a(this).data("draggable").options;
            if (d._opacity) {
                a(c.helper).css("opacity", d._opacity)
            }
        }
    });
    a.ui.plugin.add("draggable", "scroll", {
        start: function (c, d) {
            var b = a(this).data("draggable");
            if (b.scrollParent[0] != document && b.scrollParent[0].tagName != "HTML") {
                b.overflowOffset = b.scrollParent.offset()
            }
        }, drag: function (d, e) {
            var c = a(this).data("draggable"), f = c.options, b = false;
            if (c.scrollParent[0] != document && c.scrollParent[0].tagName != "HTML") {
                if (!f.axis || f.axis != "x") {
                    if ((c.overflowOffset.top + c.scrollParent[0].offsetHeight) - d.pageY < f.scrollSensitivity) {
                        c.scrollParent[0].scrollTop = b = c.scrollParent[0].scrollTop + f.scrollSpeed
                    } else {
                        if (d.pageY - c.overflowOffset.top < f.scrollSensitivity) {
                            c.scrollParent[0].scrollTop = b = c.scrollParent[0].scrollTop - f.scrollSpeed
                        }
                    }
                }
                if (!f.axis || f.axis != "y") {
                    if ((c.overflowOffset.left + c.scrollParent[0].offsetWidth) - d.pageX < f.scrollSensitivity) {
                        c.scrollParent[0].scrollLeft = b = c.scrollParent[0].scrollLeft + f.scrollSpeed
                    } else {
                        if (d.pageX - c.overflowOffset.left < f.scrollSensitivity) {
                            c.scrollParent[0].scrollLeft = b = c.scrollParent[0].scrollLeft - f.scrollSpeed
                        }
                    }
                }
            } else {
                if (!f.axis || f.axis != "x") {
                    if (d.pageY - a(doc(this)).scrollTop() < f.scrollSensitivity) {
                        b = a(doc(this)).scrollTop(a(doc(this)).scrollTop() - f.scrollSpeed)
                    } else {
                        if (a(window).height() - (d.pageY - a(doc(this)).scrollTop()) < f.scrollSensitivity) {
                            b = a(ifrDoc | document).scrollTop(a(doc(this)).scrollTop() + f.scrollSpeed)
                        }
                    }
                }
                if (!f.axis || f.axis != "y") {
                    if (d.pageX - a(doc(this)).scrollLeft() < f.scrollSensitivity) {
                        b = a(doc(this)).scrollLeft(a(doc(this)).scrollLeft() - f.scrollSpeed)
                    } else {
                        if (a(window).width() - (d.pageX - a(doc(this)).scrollLeft()) < f.scrollSensitivity) {
                            b = a(doc(this)).scrollLeft(a(doc(this)).scrollLeft() + f.scrollSpeed)
                        }
                    }
                }
            }
            if (b !== false && a.ui.ddmanager && !f.dropBehaviour) {
                a.ui.ddmanager.prepareOffsets(c, d)
            }
        }
    });
    a.ui.plugin.add("draggable", "snap", {
        start: function (c, d) {
            var b = a(this).data("draggable"), e = b.options;
            b.snapElements = [];
            a(e.snap.constructor != String ? (e.snap.items || ":data(draggable)") : e.snap).each(function () {
                var g = a(this);
                var f = g.offset();
                if (this != b.element[0]) {
                    b.snapElements.push({
                        item: this,
                        width: g.outerWidth(),
                        height: g.outerHeight(),
                        top: f.top,
                        left: f.left
                    })
                }
            })
        }, drag: function (u, p) {
            var g = a(this).data("draggable"), q = g.options;
            var y = q.snapTolerance;
            var x = p.offset.left, w = x + g.helperProportions.width, f = p.offset.top,
                e = f + g.helperProportions.height;
            for (var v = g.snapElements.length - 1; v >= 0; v--) {
                var s = g.snapElements[v].left, n = s + g.snapElements[v].width, m = g.snapElements[v].top,
                    A = m + g.snapElements[v].height;
                if (!((s - y < x && x < n + y && m - y < f && f < A + y) || (s - y < x && x < n + y && m - y < e && e < A + y) || (s - y < w && w < n + y && m - y < f && f < A + y) || (s - y < w && w < n + y && m - y < e && e < A + y))) {
                    if (g.snapElements[v].snapping) {
                        (g.options.snap.release && g.options.snap.release.call(g.element, u, a.extend(g._uiHash(), {snapItem: g.snapElements[v].item})))
                    }
                    g.snapElements[v].snapping = false;
                    continue
                }
                if (q.snapMode != "inner") {
                    var c = Math.abs(m - e) <= y;
                    var z = Math.abs(A - f) <= y;
                    var j = Math.abs(s - w) <= y;
                    var k = Math.abs(n - x) <= y;
                    if (c) {
                        p.position.top = g._convertPositionTo("relative", {
                            top: m - g.helperProportions.height,
                            left: 0
                        }).top - g.margins.top
                    }
                    if (z) {
                        p.position.top = g._convertPositionTo("relative", {top: A, left: 0}).top - g.margins.top
                    }
                    if (j) {
                        p.position.left = g._convertPositionTo("relative", {
                            top: 0,
                            left: s - g.helperProportions.width
                        }).left - g.margins.left
                    }
                    if (k) {
                        p.position.left = g._convertPositionTo("relative", {top: 0, left: n}).left - g.margins.left
                    }
                }
                var h = (c || z || j || k);
                if (q.snapMode != "outer") {
                    var c = Math.abs(m - f) <= y;
                    var z = Math.abs(A - e) <= y;
                    var j = Math.abs(s - x) <= y;
                    var k = Math.abs(n - w) <= y;
                    if (c) {
                        p.position.top = g._convertPositionTo("relative", {top: m, left: 0}).top - g.margins.top
                    }
                    if (z) {
                        p.position.top = g._convertPositionTo("relative", {
                            top: A - g.helperProportions.height,
                            left: 0
                        }).top - g.margins.top
                    }
                    if (j) {
                        p.position.left = g._convertPositionTo("relative", {top: 0, left: s}).left - g.margins.left
                    }
                    if (k) {
                        p.position.left = g._convertPositionTo("relative", {
                            top: 0,
                            left: n - g.helperProportions.width
                        }).left - g.margins.left
                    }
                }
                if (!g.snapElements[v].snapping && (c || z || j || k || h)) {
                    (g.options.snap.snap && g.options.snap.snap.call(g.element, u, a.extend(g._uiHash(), {snapItem: g.snapElements[v].item})))
                }
                g.snapElements[v].snapping = (c || z || j || k || h)
            }
        }
    });
    a.ui.plugin.add("draggable", "stack", {
        start: function (b, c) {
            var e = a(this).data("draggable").options;
            var d = a.makeArray(a(e.stack.group)).sort(function (g, f) {
                return (parseInt(a(g).css("zIndex"), 10) || e.stack.min) - (parseInt(a(f).css("zIndex"), 10) || e.stack.min)
            });
            a(d).each(function (f) {
                this.style.zIndex = e.stack.min + f
            });
            this[0].style.zIndex = e.stack.min + d.length
        }
    });
    a.ui.plugin.add("draggable", "zIndex", {
        start: function (c, d) {
            var b = a(d.helper), e = a(this).data("draggable").options;
            if (b.css("zIndex")) {
                e._zIndex = b.css("zIndex")
            }
            b.css("zIndex", e.zIndex)
        }, stop: function (b, c) {
            var d = a(this).data("draggable").options;
            if (d._zIndex) {
                a(c.helper).css("zIndex", d._zIndex)
            }
        }
    })
})(jQuery);
;/*
 * jQuery UI Droppable 1.7.2
 *
 * Copyright (c) 2009 AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT (MIT-LICENSE.txt)
 * and GPL (GPL-LICENSE.txt) licenses.
 *
 * http://docs.jquery.com/UI/Droppables
 *
 * Depends:
 *	ui.core.js
 *	ui.draggable.js
 */
(function (a) {
    a.widget("ui.droppable", {
        _init: function () {
            var c = this.options, b = c.accept;
            this.isover = 0;
            this.isout = 1;
            this.options.accept = this.options.accept && a.isFunction(this.options.accept) ? this.options.accept : function (e) {
                return e.is(b)
            };
            this.proportions = {width: this.element[0].offsetWidth, height: this.element[0].offsetHeight};
            a.ui.ddmanager.droppables[this.options.scope] = a.ui.ddmanager.droppables[this.options.scope] || [];
            a.ui.ddmanager.droppables[this.options.scope].push(this);
            (this.options.addClasses && this.element.addClass("ui-droppable"))
        }, destroy: function () {
            var b = a.ui.ddmanager.droppables[this.options.scope];
            for (var c = 0; c < b.length; c++) {
                if (b[c] == this) {
                    b.splice(c, 1)
                }
            }
            this.element.removeClass("ui-droppable ui-droppable-disabled").removeData("droppable").unbind(".droppable")
        }, _setData: function (b, c) {
            if (b == "accept") {
                this.options.accept = c && a.isFunction(c) ? c : function (e) {
                    return e.is(c)
                }
            } else {
                a.widget.prototype._setData.apply(this, arguments)
            }
        }, _activate: function (c) {
            var b = a.ui.ddmanager.current;
            if (this.options.activeClass) {
                this.element.addClass(this.options.activeClass)
            }
            (b && this._trigger("activate", c, this.ui(b)))
        }, _deactivate: function (c) {
            var b = a.ui.ddmanager.current;
            if (this.options.activeClass) {
                this.element.removeClass(this.options.activeClass)
            }
            (b && this._trigger("deactivate", c, this.ui(b)))
        }, _over: function (c) {
            var b = a.ui.ddmanager.current;
            if (!b || (b.currentItem || b.element)[0] == this.element[0]) {
                return
            }
            if (this.options.accept.call(this.element[0], (b.currentItem || b.element))) {
                if (this.options.hoverClass) {
                    this.element.addClass(this.options.hoverClass)
                }
                this._trigger("over", c, this.ui(b))
            }
        }, _out: function (c) {
            var b = a.ui.ddmanager.current;
            if (!b || (b.currentItem || b.element)[0] == this.element[0]) {
                return
            }
            if (this.options.accept.call(this.element[0], (b.currentItem || b.element))) {
                if (this.options.hoverClass) {
                    this.element.removeClass(this.options.hoverClass)
                }
                this._trigger("out", c, this.ui(b))
            }
        }, _drop: function (c, d) {
            var b = d || a.ui.ddmanager.current;
            if (!b || (b.currentItem || b.element)[0] == this.element[0]) {
                return false
            }
            var e = false;
            this.element.find(":data(droppable)").not(".ui-draggable-dragging").each(function () {
                var f = a.data(this, "droppable");
                if (f.options.greedy && a.ui.intersect(b, a.extend(f, {offset: f.element.offset()}), f.options.tolerance)) {
                    e = true;
                    return false
                }
            });
            if (e) {
                return false
            }
            if (this.options.accept.call(this.element[0], (b.currentItem || b.element))) {
                if (this.options.activeClass) {
                    this.element.removeClass(this.options.activeClass)
                }
                if (this.options.hoverClass) {
                    this.element.removeClass(this.options.hoverClass)
                }
                this._trigger("drop", c, this.ui(b));
                return this.element
            }
            return false
        }, ui: function (b) {
            return {
                draggable: (b.currentItem || b.element),
                helper: b.helper,
                position: b.position,
                absolutePosition: b.positionAbs,
                offset: b.positionAbs
            }
        }
    });
    a.extend(a.ui.droppable, {
        version: "1.7.2",
        eventPrefix: "drop",
        defaults: {
            accept: "*",
            activeClass: false,
            addClasses: true,
            greedy: false,
            hoverClass: false,
            scope: "default",
            tolerance: "intersect"
        }
    });
    a.ui.intersect = function (q, j, o) {
        if (!j.offset) {
            return false
        }
        var e = (q.positionAbs || q.position.absolute).left, d = e + q.helperProportions.width,
            n = (q.positionAbs || q.position.absolute).top, m = n + q.helperProportions.height;
        var g = j.offset.left, c = g + j.proportions.width, p = j.offset.top, k = p + j.proportions.height;
        switch (o) {
            case"fit":
                return (g < e && d < c && p < n && m < k);
                break;
            case"intersect":
                return (g < e + (q.helperProportions.width / 2) && d - (q.helperProportions.width / 2) < c && p < n + (q.helperProportions.height / 2) && m - (q.helperProportions.height / 2) < k);
                break;
            case"pointer":
                var h = ((q.positionAbs || q.position.absolute).left + (q.clickOffset || q.offset.click).left),
                    i = ((q.positionAbs || q.position.absolute).top + (q.clickOffset || q.offset.click).top),
                    f = a.ui.isOver(i, h, p, g, j.proportions.height, j.proportions.width);
                return f;
                break;
            case"touch":
                return ((n >= p && n <= k) || (m >= p && m <= k) || (n < p && m > k)) && ((e >= g && e <= c) || (d >= g && d <= c) || (e < g && d > c));
                break;
            default:
                return false;
                break
        }
    };
    a.ui.ddmanager = {
        current: null, droppables: {"default": []}, prepareOffsets: function (e, g) {
            var b = a.ui.ddmanager.droppables[e.options.scope];
            var f = g ? g.type : null;
            var h = (e.currentItem || e.element).find(":data(droppable)").andSelf();
            droppablesLoop:for (var d = 0; d < b.length; d++) {
                if (b[d].options.disabled || (e && !b[d].options.accept.call(b[d].element[0], (e.currentItem || e.element)))) {
                    continue
                }
                for (var c = 0; c < h.length; c++) {
                    if (h[c] == b[d].element[0]) {
                        b[d].proportions.height = 0;
                        continue droppablesLoop
                    }
                }
                b[d].visible = b[d].element.css("display") != "none";
                if (!b[d].visible) {
                    continue
                }
                b[d].offset = b[d].element.offset();
                b[d].proportions = {width: b[d].element[0].offsetWidth, height: b[d].element[0].offsetHeight};
                if (f == "mousedown") {
                    b[d]._activate.call(b[d], g)
                }
            }
        }, drop: function (b, c) {
            var d = false;
            a.each(a.ui.ddmanager.droppables[b.options.scope], function () {
                if (!this.options) {
                    return
                }
                if (!this.options.disabled && this.visible && a.ui.intersect(b, this, this.options.tolerance)) {
                    d = this._drop.call(this, c)
                }
                if (!this.options.disabled && this.visible && this.options.accept.call(this.element[0], (b.currentItem || b.element))) {
                    this.isout = 1;
                    this.isover = 0;
                    this._deactivate.call(this, c)
                }
            });
            return d
        }, drag: function (b, c) {
            if (b.options.refreshPositions) {
                a.ui.ddmanager.prepareOffsets(b, c)
            }
            a.each(a.ui.ddmanager.droppables[b.options.scope], function () {
                if (this.options.disabled || this.greedyChild || !this.visible) {
                    return
                }
                var e = a.ui.intersect(b, this, this.options.tolerance);
                var g = !e && this.isover == 1 ? "isout" : (e && this.isover == 0 ? "isover" : null);
                if (!g) {
                    return
                }
                var f;
                if (this.options.greedy) {
                    var d = this.element.parents(":data(droppable):eq(0)");
                    if (d.length) {
                        f = a.data(d[0], "droppable");
                        f.greedyChild = (g == "isover" ? 1 : 0)
                    }
                }
                if (f && g == "isover") {
                    f.isover = 0;
                    f.isout = 1;
                    f._out.call(f, c)
                }
                this[g] = 1;
                this[g == "isout" ? "isover" : "isout"] = 0;
                this[g == "isover" ? "_over" : "_out"].call(this, c);
                if (f && g == "isout") {
                    f.isout = 0;
                    f.isover = 1;
                    f._over.call(f, c)
                }
            })
        }
    }
})(jQuery);
;/*
 * jQuery UI Resizable 1.7.2
 *
 * Copyright (c) 2009 AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT (MIT-LICENSE.txt)
 * and GPL (GPL-LICENSE.txt) licenses.
 *
 * http://docs.jquery.com/UI/Resizables
 *
 * Depends:
 *	ui.core.js
 */
(function (c) {
    c.widget("ui.resizable", c.extend({}, c.ui.mouse, {
        _init: function () {
            var e = this, j = this.options;
            this.element.addClass("ui-resizable");
            c.extend(this, {
                _aspectRatio: !!(j.aspectRatio),
                aspectRatio: j.aspectRatio,
                originalElement: this.element,
                _proportionallyResizeElements: [],
                _helper: j.helper || j.ghost || j.animate ? j.helper || "ui-resizable-helper" : null
            });
            if (this.element[0].nodeName.match(/canvas|textarea|input|select|button|img/i)) {
                if (/relative/.test(this.element.css("position")) && c.browser.opera) {
                    this.element.css({position: "relative", top: "auto", left: "auto"})
                }
                this.element.wrap(c('<div class="ui-wrapper" style="overflow: hidden;"></div>').css({
                    position: this.element.css("position"),
                    width: this.element.outerWidth(),
                    height: this.element.outerHeight(),
                    top: this.element.css("top"),
                    left: this.element.css("left")
                }));
                this.element = this.element.parent().data("resizable", this.element.data("resizable"));
                this.elementIsWrapper = true;
                this.element.css({
                    marginLeft: this.originalElement.css("marginLeft"),
                    marginTop: this.originalElement.css("marginTop"),
                    marginRight: this.originalElement.css("marginRight"),
                    marginBottom: this.originalElement.css("marginBottom")
                });
                this.originalElement.css({marginLeft: 0, marginTop: 0, marginRight: 0, marginBottom: 0});
                this.originalResizeStyle = this.originalElement.css("resize");
                this.originalElement.css("resize", "none");
                this._proportionallyResizeElements.push(this.originalElement.css({
                    position: "static",
                    zoom: 1,
                    display: "block"
                }));
                this.originalElement.css({margin: this.originalElement.css("margin")});
                this._proportionallyResize()
            }
            this.handles = j.handles || (!c(".ui-resizable-handle", this.element).length ? "e,s,se" : {
                n: ".ui-resizable-n",
                e: ".ui-resizable-e",
                s: ".ui-resizable-s",
                w: ".ui-resizable-w",
                se: ".ui-resizable-se",
                sw: ".ui-resizable-sw",
                ne: ".ui-resizable-ne",
                nw: ".ui-resizable-nw"
            });
            if (this.handles.constructor == String) {
                if (this.handles == "all") {
                    this.handles = "n,e,s,w,se,sw,ne,nw"
                }
                var k = this.handles.split(",");
                this.handles = {};
                for (var f = 0; f < k.length; f++) {
                    var h = c.trim(k[f]), d = "ui-resizable-" + h;
                    var g = c('<div class="ui-resizable-handle ' + d + '"></div>');
                    if (/sw|se|ne|nw/.test(h)) {
                        g.css({zIndex: ++j.zIndex})
                    }
                    if ("se" == h) {
                        g.addClass("ui-icon ui-icon-gripsmall-diagonal-se")
                    }
                    this.handles[h] = ".ui-resizable-" + h;
                    this.element.append(g)
                }
            }
            this._renderAxis = function (p) {
                p = p || this.element;
                for (var m in this.handles) {
                    if (this.handles[m].constructor == String) {
                        this.handles[m] = c(this.handles[m], this.element).show()
                    }
                    if (this.elementIsWrapper && this.originalElement[0].nodeName.match(/textarea|input|select|button/i)) {
                        var n = c(this.handles[m], this.element), o = 0;
                        o = /sw|ne|nw|se|n|s/.test(m) ? n.outerHeight() : n.outerWidth();
                        var l = ["padding", /ne|nw|n/.test(m) ? "Top" : /se|sw|s/.test(m) ? "Bottom" : /^e$/.test(m) ? "Right" : "Left"].join("");
                        p.css(l, o);
                        this._proportionallyResize()
                    }
                    if (!c(this.handles[m]).length) {
                        continue
                    }
                }
            };
            this._renderAxis(this.element);
            this._handles = c(".ui-resizable-handle", this.element).disableSelection();
            this._handles.mouseover(function () {
                if (!e.resizing) {
                    if (this.className) {
                        var i = this.className.match(/ui-resizable-(se|sw|ne|nw|n|e|s|w)/i)
                    }
                    e.axis = i && i[1] ? i[1] : "se"
                }
            });
            if (j.autoHide) {
                this._handles.hide();
                c(this.element).addClass("ui-resizable-autohide").hover(function () {
                    c(this).removeClass("ui-resizable-autohide");
                    e._handles.show()
                }, function () {
                    if (!e.resizing) {
                        c(this).addClass("ui-resizable-autohide");
                        e._handles.hide()
                    }
                })
            }
            this._mouseInit()
        }, destroy: function () {
            this._mouseDestroy();
            var d = function (f) {
                c(f).removeClass("ui-resizable ui-resizable-disabled ui-resizable-resizing").removeData("resizable").unbind(".resizable").find(".ui-resizable-handle").remove()
            };
            if (this.elementIsWrapper) {
                d(this.element);
                var e = this.element;
                e.parent().append(this.originalElement.css({
                    position: e.css("position"),
                    width: e.outerWidth(),
                    height: e.outerHeight(),
                    top: e.css("top"),
                    left: e.css("left")
                })).end().remove()
            }
            this.originalElement.css("resize", this.originalResizeStyle);
            d(this.originalElement)
        }, _mouseCapture: function (e) {
            var f = false;
            for (var d in this.handles) {
                if (c(this.handles[d])[0] == e.target) {
                    f = true
                }
            }
            return this.options.disabled || !!f
        }, _mouseStart: function (f) {
            var i = this.options, e = this.element.position(), d = this.element;
            this.resizing = true;
            this.documentScroll = {top: c(document).scrollTop(), left: c(document).scrollLeft()};
            if (d.is(".ui-draggable") || (/absolute/).test(d.css("position"))) {
                d.css({position: "absolute", top: e.top, left: e.left})
            }
            if (c.browser.opera && (/relative/).test(d.css("position"))) {
                d.css({position: "relative", top: "auto", left: "auto"})
            }
            this._renderProxy();
            var j = b(this.helper.css("left")), g = b(this.helper.css("top"));
            if (i.containment) {
                j += c(i.containment).scrollLeft() || 0;
                g += c(i.containment).scrollTop() || 0
            }
            this.offset = this.helper.offset();
            this.position = {left: j, top: g};
            this.size = this._helper ? {width: d.outerWidth(), height: d.outerHeight()} : {
                width: d.width(),
                height: d.height()
            };
            this.originalSize = this._helper ? {width: d.outerWidth(), height: d.outerHeight()} : {
                width: d.width(),
                height: d.height()
            };
            this.originalPosition = {left: j, top: g};
            this.sizeDiff = {width: d.outerWidth() - d.width(), height: d.outerHeight() - d.height()};
            this.originalMousePosition = {left: f.pageX, top: f.pageY};
            this.aspectRatio = (typeof i.aspectRatio == "number") ? i.aspectRatio : ((this.originalSize.width / this.originalSize.height) || 1);
            var h = c(".ui-resizable-" + this.axis).css("cursor");
            c("body").css("cursor", h == "auto" ? this.axis + "-resize" : h);
            d.addClass("ui-resizable-resizing");
            this._propagate("start", f);
            return true
        }, _mouseDrag: function (d) {
            var g = this.helper, f = this.options, l = {}, p = this, i = this.originalMousePosition, m = this.axis;
            var q = (d.pageX - i.left) || 0, n = (d.pageY - i.top) || 0;
            var h = this._change[m];
            if (!h) {
                return false
            }
            var k = h.apply(this, [d, q, n]), j = c.browser.msie && c.browser.version < 7, e = this.sizeDiff;
            if (this._aspectRatio || d.shiftKey) {
                k = this._updateRatio(k, d)
            }
            k = this._respectSize(k, d);
            this._propagate("resize", d);
            g.css({
                top: this.position.top + "px",
                left: this.position.left + "px",
                width: this.size.width + "px",
                height: this.size.height + "px"
            });
            if (!this._helper && this._proportionallyResizeElements.length) {
                this._proportionallyResize()
            }
            this._updateCache(k);
            this._trigger("resize", d, this.ui());
            return false
        }, _mouseStop: function (g) {
            this.resizing = false;
            var h = this.options, l = this;
            if (this._helper) {
                var f = this._proportionallyResizeElements, d = f.length && (/textarea/i).test(f[0].nodeName),
                    e = d && c.ui.hasScroll(f[0], "left") ? 0 : l.sizeDiff.height, j = d ? 0 : l.sizeDiff.width;
                var m = {width: (l.size.width - j), height: (l.size.height - e)},
                    i = (parseInt(l.element.css("left"), 10) + (l.position.left - l.originalPosition.left)) || null,
                    k = (parseInt(l.element.css("top"), 10) + (l.position.top - l.originalPosition.top)) || null;
                if (!h.animate) {
                    this.element.css(c.extend(m, {top: k, left: i}))
                }
                l.helper.height(l.size.height);
                l.helper.width(l.size.width);
                if (this._helper && !h.animate) {
                    this._proportionallyResize()
                }
            }
            c("body").css("cursor", "auto");
            this.element.removeClass("ui-resizable-resizing");
            this._propagate("stop", g);
            if (this._helper) {
                this.helper.remove()
            }
            return false
        }, _updateCache: function (d) {
            var e = this.options;
            this.offset = this.helper.offset();
            if (a(d.left)) {
                this.position.left = d.left
            }
            if (a(d.top)) {
                this.position.top = d.top
            }
            if (a(d.height)) {
                this.size.height = d.height
            }
            if (a(d.width)) {
                this.size.width = d.width
            }
        }, _updateRatio: function (g, f) {
            var h = this.options, i = this.position, e = this.size, d = this.axis;
            if (g.height) {
                g.width = (e.height * this.aspectRatio)
            } else {
                if (g.width) {
                    g.height = (e.width / this.aspectRatio)
                }
            }
            if (d == "sw") {
                g.left = i.left + (e.width - g.width);
                g.top = null
            }
            if (d == "nw") {
                g.top = i.top + (e.height - g.height);
                g.left = i.left + (e.width - g.width)
            }
            return g
        }, _respectSize: function (k, f) {
            var i = this.helper, h = this.options, q = this._aspectRatio || f.shiftKey, p = this.axis,
                s = a(k.width) && h.maxWidth && (h.maxWidth < k.width),
                l = a(k.height) && h.maxHeight && (h.maxHeight < k.height),
                g = a(k.width) && h.minWidth && (h.minWidth > k.width),
                r = a(k.height) && h.minHeight && (h.minHeight > k.height);
            if (g) {
                k.width = h.minWidth
            }
            if (r) {
                k.height = h.minHeight
            }
            if (s) {
                k.width = h.maxWidth
            }
            if (l) {
                k.height = h.maxHeight
            }
            var e = this.originalPosition.left + this.originalSize.width, n = this.position.top + this.size.height;
            var j = /sw|nw|w/.test(p), d = /nw|ne|n/.test(p);
            if (g && j) {
                k.left = e - h.minWidth
            }
            if (s && j) {
                k.left = e - h.maxWidth
            }
            if (r && d) {
                k.top = n - h.minHeight
            }
            if (l && d) {
                k.top = n - h.maxHeight
            }
            var m = !k.width && !k.height;
            if (m && !k.left && k.top) {
                k.top = null
            } else {
                if (m && !k.top && k.left) {
                    k.left = null
                }
            }
            return k
        }, _proportionallyResize: function () {
            var j = this.options;
            if (!this._proportionallyResizeElements.length) {
                return
            }
            var f = this.helper || this.element;
            for (var e = 0; e < this._proportionallyResizeElements.length; e++) {
                var g = this._proportionallyResizeElements[e];
                if (!this.borderDif) {
                    var d = [g.css("borderTopWidth"), g.css("borderRightWidth"), g.css("borderBottomWidth"), g.css("borderLeftWidth")],
                        h = [g.css("paddingTop"), g.css("paddingRight"), g.css("paddingBottom"), g.css("paddingLeft")];
                    this.borderDif = c.map(d, function (k, m) {
                        var l = parseInt(k, 10) || 0, n = parseInt(h[m], 10) || 0;
                        return l + n
                    })
                }
                if (c.browser.msie && !(!(c(f).is(":hidden") || c(f).parents(":hidden").length))) {
                    continue
                }
                g.css({
                    height: (f.height() - this.borderDif[0] - this.borderDif[2]) || 0,
                    width: (f.width() - this.borderDif[1] - this.borderDif[3]) || 0
                })
            }
        }, _renderProxy: function () {
            var e = this.element, h = this.options;
            this.elementOffset = e.offset();
            if (this._helper) {
                this.helper = this.helper || c('<div style="overflow:hidden;"></div>');
                var d = c.browser.msie && c.browser.version < 7, f = (d ? 1 : 0), g = (d ? 2 : -1);
                this.helper.addClass(this._helper).css({
                    width: this.element.outerWidth() + g,
                    height: this.element.outerHeight() + g,
                    position: "absolute",
                    left: this.elementOffset.left - f + "px",
                    top: this.elementOffset.top - f + "px",
                    zIndex: ++h.zIndex
                });
                this.helper.appendTo("body").disableSelection()
            } else {
                this.helper = this.element
            }
        }, _change: {
            e: function (f, e, d) {
                return {width: this.originalSize.width + e}
            }, w: function (g, e, d) {
                var i = this.options, f = this.originalSize, h = this.originalPosition;
                return {left: h.left + e, width: f.width - e}
            }, n: function (g, e, d) {
                var i = this.options, f = this.originalSize, h = this.originalPosition;
                return {top: h.top + d, height: f.height - d}
            }, s: function (f, e, d) {
                return {height: this.originalSize.height + d}
            }, se: function (f, e, d) {
                return c.extend(this._change.s.apply(this, arguments), this._change.e.apply(this, [f, e, d]))
            }, sw: function (f, e, d) {
                return c.extend(this._change.s.apply(this, arguments), this._change.w.apply(this, [f, e, d]))
            }, ne: function (f, e, d) {
                return c.extend(this._change.n.apply(this, arguments), this._change.e.apply(this, [f, e, d]))
            }, nw: function (f, e, d) {
                return c.extend(this._change.n.apply(this, arguments), this._change.w.apply(this, [f, e, d]))
            }
        }, _propagate: function (e, d) {
            c.ui.plugin.call(this, e, [d, this.ui()]);
            (e != "resize" && this._trigger(e, d, this.ui()))
        }, plugins: {}, ui: function () {
            return {
                originalElement: this.originalElement,
                element: this.element,
                helper: this.helper,
                position: this.position,
                size: this.size,
                originalSize: this.originalSize,
                originalPosition: this.originalPosition
            }
        }
    }));
    c.extend(c.ui.resizable, {
        version: "1.7.2",
        eventPrefix: "resize",
        defaults: {
            alsoResize: false,
            animate: false,
            animateDuration: "slow",
            animateEasing: "swing",
            aspectRatio: false,
            autoHide: false,
            cancel: ":input,option",
            containment: false,
            delay: 0,
            distance: 1,
            ghost: false,
            grid: false,
            handles: "e,s,se",
            helper: false,
            maxHeight: null,
            maxWidth: null,
            minHeight: 10,
            minWidth: 10,
            zIndex: 1000
        }
    });
    c.ui.plugin.add("resizable", "alsoResize", {
        start: function (e, f) {
            var d = c(this).data("resizable"), g = d.options;
            _store = function (h) {
                c(h).each(function () {
                    c(this).data("resizable-alsoresize", {
                        width: parseInt(c(this).width(), 10),
                        height: parseInt(c(this).height(), 10),
                        left: parseInt(c(this).css("left"), 10),
                        top: parseInt(c(this).css("top"), 10)
                    })
                })
            };
            if (typeof (g.alsoResize) == "object" && !g.alsoResize.parentNode) {
                if (g.alsoResize.length) {
                    g.alsoResize = g.alsoResize[0];
                    _store(g.alsoResize)
                } else {
                    c.each(g.alsoResize, function (h, i) {
                        _store(h)
                    })
                }
            } else {
                _store(g.alsoResize)
            }
        }, resize: function (f, h) {
            var e = c(this).data("resizable"), i = e.options, g = e.originalSize, k = e.originalPosition;
            var j = {
                height: (e.size.height - g.height) || 0,
                width: (e.size.width - g.width) || 0,
                top: (e.position.top - k.top) || 0,
                left: (e.position.left - k.left) || 0
            }, d = function (l, m) {
                c(l).each(function () {
                    var p = c(this), q = c(this).data("resizable-alsoresize"), o = {},
                        n = m && m.length ? m : ["width", "height", "top", "left"];
                    c.each(n || ["width", "height", "top", "left"], function (r, t) {
                        var s = (q[t] || 0) + (j[t] || 0);
                        if (s && s >= 0) {
                            o[t] = s || null
                        }
                    });
                    if (/relative/.test(p.css("position")) && c.browser.opera) {
                        e._revertToRelativePosition = true;
                        p.css({position: "absolute", top: "auto", left: "auto"})
                    }
                    p.css(o)
                })
            };
            if (typeof (i.alsoResize) == "object" && !i.alsoResize.nodeType) {
                c.each(i.alsoResize, function (l, m) {
                    d(l, m)
                })
            } else {
                d(i.alsoResize)
            }
        }, stop: function (e, f) {
            var d = c(this).data("resizable");
            if (d._revertToRelativePosition && c.browser.opera) {
                d._revertToRelativePosition = false;
                el.css({position: "relative"})
            }
            c(this).removeData("resizable-alsoresize-start")
        }
    });
    c.ui.plugin.add("resizable", "animate", {
        stop: function (h, m) {
            var n = c(this).data("resizable"), i = n.options;
            var g = n._proportionallyResizeElements, d = g.length && (/textarea/i).test(g[0].nodeName),
                e = d && c.ui.hasScroll(g[0], "left") ? 0 : n.sizeDiff.height, k = d ? 0 : n.sizeDiff.width;
            var f = {width: (n.size.width - k), height: (n.size.height - e)},
                j = (parseInt(n.element.css("left"), 10) + (n.position.left - n.originalPosition.left)) || null,
                l = (parseInt(n.element.css("top"), 10) + (n.position.top - n.originalPosition.top)) || null;
            n.element.animate(c.extend(f, l && j ? {top: l, left: j} : {}), {
                duration: i.animateDuration,
                easing: i.animateEasing,
                step: function () {
                    var o = {
                        width: parseInt(n.element.css("width"), 10),
                        height: parseInt(n.element.css("height"), 10),
                        top: parseInt(n.element.css("top"), 10),
                        left: parseInt(n.element.css("left"), 10)
                    };
                    if (g && g.length) {
                        c(g[0]).css({width: o.width, height: o.height})
                    }
                    n._updateCache(o);
                    n._propagate("resize", h)
                }
            })
        }
    });
    c.ui.plugin.add("resizable", "containment", {
        start: function (e, q) {
            var s = c(this).data("resizable"), i = s.options, k = s.element;
            var f = i.containment, j = (f instanceof c) ? f.get(0) : (/parent/.test(f)) ? k.parent().get(0) : f;
            if (!j) {
                return
            }
            s.containerElement = c(j);
            if (/document/.test(f) || f == document) {
                s.containerOffset = {left: 0, top: 0};
                s.containerPosition = {left: 0, top: 0};
                s.parentData = {
                    element: c(document),
                    left: 0,
                    top: 0,
                    width: c(document).width(),
                    height: c(document).height() || doc(this).body.parentNode.scrollHeight
                }
            } else {
                var m = c(j), h = [];
                c(["Top", "Right", "Left", "Bottom"]).each(function (p, o) {
                    h[p] = b(m.css("padding" + o))
                });
                s.containerOffset = m.offset();
                s.containerPosition = m.position();
                s.containerSize = {height: (m.innerHeight() - h[3]), width: (m.innerWidth() - h[1])};
                var n = s.containerOffset, d = s.containerSize.height, l = s.containerSize.width,
                    g = (c.ui.hasScroll(j, "left") ? j.scrollWidth : l), r = (c.ui.hasScroll(j) ? j.scrollHeight : d);
                s.parentData = {element: j, left: n.left, top: n.top, width: g, height: r}
            }
        }, resize: function (f, p) {
            var s = c(this).data("resizable"), h = s.options, e = s.containerSize, n = s.containerOffset, l = s.size,
                m = s.position, q = s._aspectRatio || f.shiftKey, d = {top: 0, left: 0}, g = s.containerElement;
            if (g[0] != document && (/static/).test(g.css("position"))) {
                d = n
            }
            if (m.left < (s._helper ? n.left : 0)) {
                s.size.width = s.size.width + (s._helper ? (s.position.left - n.left) : (s.position.left - d.left));
                if (q) {
                    s.size.height = s.size.width / h.aspectRatio
                }
                s.position.left = h.helper ? n.left : 0
            }
            if (m.top < (s._helper ? n.top : 0)) {
                s.size.height = s.size.height + (s._helper ? (s.position.top - n.top) : s.position.top);
                if (q) {
                    s.size.width = s.size.height * h.aspectRatio
                }
                s.position.top = s._helper ? n.top : 0
            }
            s.offset.left = s.parentData.left + s.position.left;
            s.offset.top = s.parentData.top + s.position.top;
            var k = Math.abs((s._helper ? s.offset.left - d.left : (s.offset.left - d.left)) + s.sizeDiff.width),
                r = Math.abs((s._helper ? s.offset.top - d.top : (s.offset.top - n.top)) + s.sizeDiff.height);
            var j = s.containerElement.get(0) == s.element.parent().get(0),
                i = /relative|absolute/.test(s.containerElement.css("position"));
            if (j && i) {
                k -= s.parentData.left
            }
            if (k + s.size.width >= s.parentData.width) {
                s.size.width = s.parentData.width - k;
                if (q) {
                    s.size.height = s.size.width / s.aspectRatio
                }
            }
            if (r + s.size.height >= s.parentData.height) {
                s.size.height = s.parentData.height - r;
                if (q) {
                    s.size.width = s.size.height * s.aspectRatio
                }
            }
        }, stop: function (e, m) {
            var p = c(this).data("resizable"), f = p.options, k = p.position, l = p.containerOffset,
                d = p.containerPosition, g = p.containerElement;
            var i = c(p.helper), q = i.offset(), n = i.outerWidth() - p.sizeDiff.width,
                j = i.outerHeight() - p.sizeDiff.height;
            if (p._helper && !f.animate && (/relative/).test(g.css("position"))) {
                c(this).css({left: q.left - d.left - l.left, width: n, height: j})
            }
            if (p._helper && !f.animate && (/static/).test(g.css("position"))) {
                c(this).css({left: q.left - d.left - l.left, width: n, height: j})
            }
        }
    });
    c.ui.plugin.add("resizable", "ghost", {
        start: function (f, g) {
            var d = c(this).data("resizable"), h = d.options, e = d.size;
            d.ghost = d.originalElement.clone();
            d.ghost.css({
                opacity: 0.25,
                display: "block",
                position: "relative",
                height: e.height,
                width: e.width,
                margin: 0,
                left: 0,
                top: 0
            }).addClass("ui-resizable-ghost").addClass(typeof h.ghost == "string" ? h.ghost : "");
            d.ghost.appendTo(d.helper)
        }, resize: function (e, f) {
            var d = c(this).data("resizable"), g = d.options;
            if (d.ghost) {
                d.ghost.css({position: "relative", height: d.size.height, width: d.size.width})
            }
        }, stop: function (e, f) {
            var d = c(this).data("resizable"), g = d.options;
            if (d.ghost && d.helper) {
                d.helper.get(0).removeChild(d.ghost.get(0))
            }
        }
    });
    c.ui.plugin.add("resizable", "grid", {
        resize: function (d, l) {
            var n = c(this).data("resizable"), g = n.options, j = n.size, h = n.originalSize, i = n.originalPosition,
                m = n.axis, k = g._aspectRatio || d.shiftKey;
            g.grid = typeof g.grid == "number" ? [g.grid, g.grid] : g.grid;
            var f = Math.round((j.width - h.width) / (g.grid[0] || 1)) * (g.grid[0] || 1),
                e = Math.round((j.height - h.height) / (g.grid[1] || 1)) * (g.grid[1] || 1);
            if (/^(se|s|e)$/.test(m)) {
                n.size.width = h.width + f;
                n.size.height = h.height + e
            } else {
                if (/^(ne)$/.test(m)) {
                    n.size.width = h.width + f;
                    n.size.height = h.height + e;
                    n.position.top = i.top - e
                } else {
                    if (/^(sw)$/.test(m)) {
                        n.size.width = h.width + f;
                        n.size.height = h.height + e;
                        n.position.left = i.left - f
                    } else {
                        n.size.width = h.width + f;
                        n.size.height = h.height + e;
                        n.position.top = i.top - e;
                        n.position.left = i.left - f
                    }
                }
            }
        }
    });
    var b = function (d) {
        return parseInt(d, 10) || 0
    };
    var a = function (d) {
        return !isNaN(parseInt(d, 10))
    }
})(jQuery);
;/*
 * jQuery UI Sortable 1.7.2
 *
 * Copyright (c) 2009 AUTHORS.txt (http://jqueryui.com/about)
 * Dual licensed under the MIT (MIT-LICENSE.txt)
 * and GPL (GPL-LICENSE.txt) licenses.
 *
 * http://docs.jquery.com/UI/Sortables
 *
 * Depends:
 *	ui.core.js
 */
(function (a) {
    a.widget("ui.sortable", a.extend({}, a.ui.mouse, {
        _init: function () {
            var b = this.options;
            this.containerCache = {};
            this.element.addClass("ui-sortable");
            this.refresh();
            this.floating = this.items.length ? (/left|right/).test(this.items[0].item.css("float")) : false;
            this.offset = this.element.offset();
            this._mouseInit()
        }, destroy: function () {
            this.element.removeClass("ui-sortable ui-sortable-disabled").removeData("sortable").unbind(".sortable");
            this._mouseDestroy();
            for (var b = this.items.length - 1; b >= 0; b--) {
                this.items[b].item.removeData("sortable-item")
            }
        }, _mouseCapture: function (e, f) {
            if (this.reverting) {
                return false
            }
            if (this.options.disabled || this.options.type == "static") {
                return false
            }
            this._refreshItems(e);
            var d = null, c = this, b = a(e.target).parents().each(function () {
                if (a.data(this, "sortable-item") == c) {
                    d = a(this, ifrDoc);
                    return false
                }
            });
            if (a.data(e.target, "sortable-item") == c) {
                d = a(e.target, ifrDoc)
            }
            if (!d) {
                return false
            }
            if (this.options.handle && !f) {
                var g = false;
                a(this.options.handle, d).find("*").andSelf().each(function () {
                    if (this == e.target) {
                        g = true
                    }
                });
                if (!g) {
                    return false
                }
            }
            this.currentItem = a(d, ifrDoc);
            this._removeCurrentsFromItems();
            return true
        }, _mouseStart: function (e, f, b) {
            var g = this.options, c = this;
            this.currentContainer = this;
            this.refreshPositions();
            this.helper = this._createHelper(e);
            this._cacheHelperProportions();
            this._cacheMargins();
            this.scrollParent = this.helper.scrollParent();
            this.offset = this.currentItem.offset();
            this.offset = {top: this.offset.top - this.margins.top, left: this.offset.left - this.margins.left};
            this.helper.css("position", "absolute");
            this.cssPosition = this.helper.css("position");
            a.extend(this.offset, {
                click: {left: e.pageX - this.offset.left, top: e.pageY - this.offset.top},
                parent: this._getParentOffset(),
                relative: this._getRelativeOffset()
            });
            this.originalPosition = this._generatePosition(e);
            this.originalPageX = e.pageX;
            this.originalPageY = e.pageY;
            if (g.cursorAt) {
                this._adjustOffsetFromHelper(g.cursorAt)
            }
            this.domPosition = {prev: this.currentItem.prev()[0], parent: this.currentItem.parent()[0]};
            if (this.helper[0] != this.currentItem[0]) {
                this.currentItem.hide()
            }
            this._createPlaceholder();
            if (g.containment) {
                this._setContainment()
            }
            if (g.cursor) {
                if (a("body").css("cursor")) {
                    this._storedCursor = a("body").css("cursor")
                }
                a("body").css("cursor", g.cursor)
            }
            if (g.opacity) {
                if (this.helper.css("opacity")) {
                    this._storedOpacity = this.helper.css("opacity")
                }
                this.helper.css("opacity", g.opacity)
            }
            if (g.zIndex) {
                if (this.helper.css("zIndex")) {
                    this._storedZIndex = this.helper.css("zIndex")
                }
                this.helper.css("zIndex", g.zIndex)
            }
            if (this.scrollParent[0] != document && this.scrollParent[0].tagName != "HTML") {
                this.overflowOffset = this.scrollParent.offset()
            }
            this._trigger("start", e, this._uiHash());
            if (!this._preserveHelperProportions) {
                this._cacheHelperProportions()
            }
            if (!b) {
                for (var d = this.containers.length - 1; d >= 0; d--) {
                    this.containers[d]._trigger("activate", e, c._uiHash(this))
                }
            }
            if (a.ui.ddmanager) {
                a.ui.ddmanager.current = this
            }
            if (a.ui.ddmanager && !g.dropBehaviour) {
                a.ui.ddmanager.prepareOffsets(this, e)
            }
            this.dragging = true;
            this.helper.addClass("ui-sortable-helper");
            this._mouseDrag(e);
            return true
        }, _mouseDrag: function (f) {
            this.position = this._generatePosition(f);
            this.positionAbs = this._convertPositionTo("absolute");
            if (!this.lastPositionAbs) {
                this.lastPositionAbs = this.positionAbs
            }
            if (this.options.scroll) {
                var g = this.options, b = false;
                if (this.scrollParent[0] != document && this.scrollParent[0].tagName != "HTML") {
                    if ((this.overflowOffset.top + this.scrollParent[0].offsetHeight) - f.pageY < g.scrollSensitivity) {
                        this.scrollParent[0].scrollTop = b = this.scrollParent[0].scrollTop + g.scrollSpeed
                    } else {
                        if (f.pageY - this.overflowOffset.top < g.scrollSensitivity) {
                            this.scrollParent[0].scrollTop = b = this.scrollParent[0].scrollTop - g.scrollSpeed
                        }
                    }
                    if ((this.overflowOffset.left + this.scrollParent[0].offsetWidth) - f.pageX < g.scrollSensitivity) {
                        this.scrollParent[0].scrollLeft = b = this.scrollParent[0].scrollLeft + g.scrollSpeed
                    } else {
                        if (f.pageX - this.overflowOffset.left < g.scrollSensitivity) {
                            this.scrollParent[0].scrollLeft = b = this.scrollParent[0].scrollLeft - g.scrollSpeed
                        }
                    }
                } else {
                    if (f.pageY - a(doc(this)).scrollTop() < g.scrollSensitivity) {
                        b = a(doc(this)).scrollTop(a(doc(this)).scrollTop() - g.scrollSpeed)
                    } else {
                        if (a(window).height() - (f.pageY - a(doc(this)).scrollTop()) < g.scrollSensitivity) {
                            b = a(doc(this)).scrollTop(a(doc(this)).scrollTop() + g.scrollSpeed)
                        }
                    }
                    if (f.pageX - a(doc(this)).scrollLeft() < g.scrollSensitivity) {
                        b = a(doc(this)).scrollLeft(a(doc(this)).scrollLeft() - g.scrollSpeed)
                    } else {
                        if (a(window).width() - (f.pageX - a(doc(this)).scrollLeft()) < g.scrollSensitivity) {
                            b = a(doc(this)).scrollLeft(a(doc(this)).scrollLeft() + g.scrollSpeed)
                        }
                    }
                }
                if (b !== false && a.ui.ddmanager && !g.dropBehaviour) {
                    a.ui.ddmanager.prepareOffsets(this, f)
                }
            }
            this.positionAbs = this._convertPositionTo("absolute");
            if (!this.options.axis || this.options.axis != "y") {
                this.helper[0].style.left = this.position.left + "px"
            }
            if (!this.options.axis || this.options.axis != "x") {
                this.helper[0].style.top = this.position.top + "px"
            }
            for (var d = this.items.length - 1; d >= 0; d--) {
                var e = this.items[d], c = e.item[0], h = this._intersectsWithPointer(e);
                if (!h) {
                    continue
                }
                if (c != this.currentItem[0] && this.placeholder[h == 1 ? "next" : "prev"]()[0] != c && !a.ui.contains(this.placeholder[0], c) && (this.options.type == "semi-dynamic" ? !a.ui.contains(this.element[0], c) : true)) {
                    this.direction = h == 1 ? "down" : "up";
                    if (this.options.tolerance == "pointer" || this._intersectsWithSides(e)) {
                        this._rearrange(f, e)
                    } else {
                        break
                    }
                    this._trigger("change", f, this._uiHash());
                    break
                }
            }
            this._contactContainers(f);
            if (a.ui.ddmanager) {
                a.ui.ddmanager.drag(this, f)
            }
            this._trigger("sort", f, this._uiHash());
            this.lastPositionAbs = this.positionAbs;
            return false
        }, _mouseStop: function (c, d) {
            if (!c) {
                return
            }
            if (a.ui.ddmanager && !this.options.dropBehaviour) {
                a.ui.ddmanager.drop(this, c)
            }
            if (this.options.revert) {
                var b = this;
                var e = b.placeholder.offset();
                b.reverting = true;
                a(this.helper).animate({
                    left: e.left - this.offset.parent.left - b.margins.left + (this.offsetParent[0] == doc(this).body ? 0 : this.offsetParent[0].scrollLeft),
                    top: e.top - this.offset.parent.top - b.margins.top + (this.offsetParent[0] == (doc(this).body) ? 0 : this.offsetParent[0].scrollTop)
                }, parseInt(this.options.revert, 10) || 500, function () {
                    b._clear(c)
                })
            } else {
                this._clear(c, d)
            }
            return false
        }, cancel: function () {
            var b = this;
            if (this.dragging) {
                this._mouseUp();
                if (this.options.helper == "original") {
                    this.currentItem.css(this._storedCSS).removeClass("ui-sortable-helper")
                } else {
                    this.currentItem.show()
                }
                for (var c = this.containers.length - 1; c >= 0; c--) {
                    this.containers[c]._trigger("deactivate", null, b._uiHash(this));
                    if (this.containers[c].containerCache.over) {
                        this.containers[c]._trigger("out", null, b._uiHash(this));
                        this.containers[c].containerCache.over = 0
                    }
                }
            }
            if (this.placeholder[0].parentNode) {
                this.placeholder[0].parentNode.removeChild(this.placeholder[0])
            }
            if (this.options.helper != "original" && this.helper && this.helper[0].parentNode) {
                this.helper.remove()
            }
            a.extend(this, {helper: null, dragging: false, reverting: false, _noFinalSort: null});
            if (this.domPosition.prev) {
                a(this.domPosition.prev).after(this.currentItem)
            } else {
                a(this.domPosition.parent).prepend(this.currentItem)
            }
            return true
        }, serialize: function (d) {
            var b = this._getItemsAsjQuery(d && d.connected);
            var c = [];
            d = d || {};
            a(b).each(function () {
                var e = (a(d.item || this).attr(d.attribute || "id") || "").match(d.expression || (/(.+)[-=_](.+)/));
                if (e) {
                    c.push((d.key || e[1] + "[]") + "=" + (d.key && d.expression ? e[1] : e[2]))
                }
            });
            return c.join("&")
        }, toArray: function (d) {
            var b = this._getItemsAsjQuery(d && d.connected);
            var c = [];
            d = d || {};
            b.each(function () {
                c.push(a(d.item || this).attr(d.attribute || "id") || "")
            });
            return c
        }, _intersectsWith: function (m) {
            var e = this.positionAbs.left, d = e + this.helperProportions.width, k = this.positionAbs.top,
                j = k + this.helperProportions.height;
            var f = m.left, c = f + m.width, n = m.top, i = n + m.height;
            var o = this.offset.click.top, h = this.offset.click.left;
            var g = (k + o) > n && (k + o) < i && (e + h) > f && (e + h) < c;
            if (this.options.tolerance == "pointer" || this.options.forcePointerForContainers || (this.options.tolerance != "pointer" && this.helperProportions[this.floating ? "width" : "height"] > m[this.floating ? "width" : "height"])) {
                return g
            } else {
                return (f < e + (this.helperProportions.width / 2) && d - (this.helperProportions.width / 2) < c && n < k + (this.helperProportions.height / 2) && j - (this.helperProportions.height / 2) < i)
            }
        }, _intersectsWithPointer: function (d) {
            var e = a.ui.isOverAxis(this.positionAbs.top + this.offset.click.top, d.top, d.height),
                c = a.ui.isOverAxis(this.positionAbs.left + this.offset.click.left, d.left, d.width), g = e && c,
                b = this._getDragVerticalDirection(), f = this._getDragHorizontalDirection();
            if (!g) {
                return false
            }
            return this.floating ? (((f && f == "right") || b == "down") ? 2 : 1) : (b && (b == "down" ? 2 : 1))
        }, _intersectsWithSides: function (e) {
            var c = a.ui.isOverAxis(this.positionAbs.top + this.offset.click.top, e.top + (e.height / 2), e.height),
                d = a.ui.isOverAxis(this.positionAbs.left + this.offset.click.left, e.left + (e.width / 2), e.width),
                b = this._getDragVerticalDirection(), f = this._getDragHorizontalDirection();
            if (this.floating && f) {
                return ((f == "right" && d) || (f == "left" && !d))
            } else {
                return b && ((b == "down" && c) || (b == "up" && !c))
            }
        }, _getDragVerticalDirection: function () {
            var b = this.positionAbs.top - this.lastPositionAbs.top;
            return b != 0 && (b > 0 ? "down" : "up")
        }, _getDragHorizontalDirection: function () {
            var b = this.positionAbs.left - this.lastPositionAbs.left;
            return b != 0 && (b > 0 ? "right" : "left")
        }, refresh: function (b) {
            this._refreshItems(b);
            this.refreshPositions()
        }, _connectWith: function () {
            var b = this.options;
            return b.connectWith.constructor == String ? [b.connectWith] : b.connectWith
        }, _getItemsAsjQuery: function (b) {
            var l = this;
            var g = [];
            var e = [];
            var h = this._connectWith();
            if (h && b) {
                for (var d = h.length - 1; d >= 0; d--) {
                    var k = a(h[d]);
                    for (var c = k.length - 1; c >= 0; c--) {
                        var f = a.data(k[c], "sortable");
                        if (f && f != this && !f.options.disabled) {
                            e.push([a.isFunction(f.options.items) ? f.options.items.call(f.element) : a(f.options.items, f.element).not(".ui-sortable-helper"), f])
                        }
                    }
                }
            }
            e.push([a.isFunction(this.options.items) ? this.options.items.call(this.element, null, {
                options: this.options,
                item: this.currentItem
            }) : a(this.options.items, this.element).not(".ui-sortable-helper"), this]);
            for (var d = e.length - 1; d >= 0; d--) {
                e[d][0].each(function () {
                    g.push(this)
                })
            }
            return a(g)
        }, _removeCurrentsFromItems: function () {
            var d = this.currentItem.find(":data(sortable-item)");
            for (var c = 0; c < this.items.length; c++) {
                for (var b = 0; b < d.length; b++) {
                    if (d[b] == this.items[c].item[0]) {
                        this.items.splice(c, 1)
                    }
                }
            }
        }, _refreshItems: function (b) {
            this.items = [];
            this.containers = [this];
            var h = this.items;
            var p = this;
            var f = [[a.isFunction(this.options.items) ? this.options.items.call(this.element[0], b, {item: this.currentItem}) : a(this.options.items, this.element), this]];
            var l = this._connectWith();
            if (l) {
                for (var e = l.length - 1; e >= 0; e--) {
                    var m = a(l[e]);
                    for (var d = m.length - 1; d >= 0; d--) {
                        var g = a.data(m[d], "sortable");
                        if (g && g != this && !g.options.disabled) {
                            f.push([a.isFunction(g.options.items) ? g.options.items.call(g.element[0], b, {item: this.currentItem}) : a(g.options.items, g.element), g]);
                            this.containers.push(g)
                        }
                    }
                }
            }
            for (var e = f.length - 1; e >= 0; e--) {
                var k = f[e][1];
                var c = f[e][0];
                for (var d = 0, n = c.length; d < n; d++) {
                    var o = a(c[d]);
                    o.data("sortable-item", k);
                    h.push({item: o, instance: k, width: 0, height: 0, left: 0, top: 0})
                }
            }
        }, refreshPositions: function (b) {
            if (this.offsetParent && this.helper) {
                this.offset.parent = this._getParentOffset()
            }
            for (var d = this.items.length - 1; d >= 0; d--) {
                var e = this.items[d];
                if (e.instance != this.currentContainer && this.currentContainer && e.item[0] != this.currentItem[0]) {
                    continue
                }
                var c = this.options.toleranceElement ? a(this.options.toleranceElement, e.item) : e.item;
                if (!b) {
                    e.width = c.outerWidth();
                    e.height = c.outerHeight()
                }
                var f = c.offset();
                e.left = f.left;
                e.top = f.top
            }
            if (this.options.custom && this.options.custom.refreshContainers) {
                this.options.custom.refreshContainers.call(this)
            } else {
                for (var d = this.containers.length - 1; d >= 0; d--) {
                    var f = this.containers[d].element.offset();
                    this.containers[d].containerCache.left = f.left;
                    this.containers[d].containerCache.top = f.top;
                    this.containers[d].containerCache.width = this.containers[d].element.outerWidth();
                    this.containers[d].containerCache.height = this.containers[d].element.outerHeight()
                }
            }
        }, _createPlaceholder: function (d) {
            var b = d || this, e = b.options;
            if (!e.placeholder || e.placeholder.constructor == String) {
                var c = e.placeholder;
                e.placeholder = {
                    element: function () {
                        var f = a(ifrDoc.createElement(b.currentItem[0].nodeName)).addClass(c || b.currentItem[0].className + " ui-sortable-placeholder").removeClass("ui-sortable-helper")[0];
                        if (!c) {
                            f.style.visibility = "hidden"
                        }
                        return f
                    }, update: function (f, g) {
                        if (c && !e.forcePlaceholderSize) {
                            return
                        }
                        if (!g.height()) {
                            g.height(b.currentItem.innerHeight() - parseInt(b.currentItem.css("paddingTop") || 0, 10) - parseInt(b.currentItem.css("paddingBottom") || 0, 10))
                        }
                        if (!g.width()) {
                            g.width(b.currentItem.innerWidth() - parseInt(b.currentItem.css("paddingLeft") || 0, 10) - parseInt(b.currentItem.css("paddingRight") || 0, 10))
                        }
                    }
                }
            }
            b.placeholder = a(e.placeholder.element.call(b.element, b.currentItem));
            b.currentItem.after(b.placeholder);
            e.placeholder.update(b, b.placeholder)
        }, _contactContainers: function (d) {
            for (var c = this.containers.length - 1; c >= 0; c--) {
                if (this._intersectsWith(this.containers[c].containerCache)) {
                    if (!this.containers[c].containerCache.over) {
                        if (this.currentContainer != this.containers[c]) {
                            var h = 10000;
                            var g = null;
                            var e = this.positionAbs[this.containers[c].floating ? "left" : "top"];
                            for (var b = this.items.length - 1; b >= 0; b--) {
                                if (!a.ui.contains(this.containers[c].element[0], this.items[b].item[0])) {
                                    continue
                                }
                                var f = this.items[b][this.containers[c].floating ? "left" : "top"];
                                if (Math.abs(f - e) < h) {
                                    h = Math.abs(f - e);
                                    g = this.items[b]
                                }
                            }
                            if (!g && !this.options.dropOnEmpty) {
                                continue
                            }
                            this.currentContainer = this.containers[c];
                            g ? this._rearrange(d, g, null, true) : this._rearrange(d, null, this.containers[c].element, true);
                            this._trigger("change", d, this._uiHash());
                            this.containers[c]._trigger("change", d, this._uiHash(this));
                            this.options.placeholder.update(this.currentContainer, this.placeholder)
                        }
                        this.containers[c]._trigger("over", d, this._uiHash(this));
                        this.containers[c].containerCache.over = 1
                    }
                } else {
                    if (this.containers[c].containerCache.over) {
                        this.containers[c]._trigger("out", d, this._uiHash(this));
                        this.containers[c].containerCache.over = 0
                    }
                }
            }
        }, _createHelper: function (c) {
            var d = this.options;
            var b = a.isFunction(d.helper) ? a(d.helper.apply(this.element[0], [c, this.currentItem])) : (d.helper == "clone" ? this.currentItem.clone() : this.currentItem);
            if (!b.parents("body").length) {
                a(d.appendTo != "parent" ? d.appendTo : this.currentItem[0].parentNode)[0].appendChild(b[0])
            }
            if (b[0] == this.currentItem[0]) {
                this._storedCSS = {
                    width: this.currentItem[0].style.width,
                    height: this.currentItem[0].style.height,
                    position: this.currentItem.css("position"),
                    top: this.currentItem.css("top"),
                    left: this.currentItem.css("left")
                }
            }
            if (b[0].style.width == "" || d.forceHelperSize) {
                b.width(this.currentItem.width())
            }
            if (b[0].style.height == "" || d.forceHelperSize) {
                b.height(this.currentItem.height())
            }
            return b
        }, _adjustOffsetFromHelper: function (b) {
            if (b.left != undefined) {
                this.offset.click.left = b.left + this.margins.left
            }
            if (b.right != undefined) {
                this.offset.click.left = this.helperProportions.width - b.right + this.margins.left
            }
            if (b.top != undefined) {
                this.offset.click.top = b.top + this.margins.top
            }
            if (b.bottom != undefined) {
                this.offset.click.top = this.helperProportions.height - b.bottom + this.margins.top
            }
        }, _getParentOffset: function () {
            this.offsetParent = this.helper.offsetParent();
            var b = this.offsetParent.offset();
            if (this.cssPosition == "absolute" && this.scrollParent[0] != document && a.ui.contains(this.scrollParent[0], this.offsetParent[0])) {
                b.left += this.scrollParent.scrollLeft();
                b.top += this.scrollParent.scrollTop()
            }
            if ((this.offsetParent[0] == doc(this).body) || (this.offsetParent[0].tagName && this.offsetParent[0].tagName.toLowerCase() == "html" && a.browser.msie)) {
                b = {top: 0, left: 0}
            }
            return {
                top: b.top + (parseInt(this.offsetParent.css("borderTopWidth"), 10) || 0),
                left: b.left + (parseInt(this.offsetParent.css("borderLeftWidth"), 10) || 0)
            }
        }, _getRelativeOffset: function () {
            if (this.cssPosition == "relative") {
                var b = this.currentItem.position();
                return {
                    top: b.top - (parseInt(this.helper.css("top"), 10) || 0) + this.scrollParent.scrollTop(),
                    left: b.left - (parseInt(this.helper.css("left"), 10) || 0) + this.scrollParent.scrollLeft()
                }
            } else {
                return {top: 0, left: 0}
            }
        }, _cacheMargins: function () {
            this.margins = {
                left: (parseInt(this.currentItem.css("marginLeft"), 10) || 0),
                top: (parseInt(this.currentItem.css("marginTop"), 10) || 0)
            }
        }, _cacheHelperProportions: function () {
            this.helperProportions = {width: this.helper.outerWidth(), height: this.helper.outerHeight()}
        }, _setContainment: function () {
            var e = this.options;
            if (e.containment == "parent") {
                e.containment = this.helper[0].parentNode
            }
            if (e.containment == "document" || e.containment == "window") {
                this.containment = [0 - this.offset.relative.left - this.offset.parent.left, 0 - this.offset.relative.top - this.offset.parent.top, a(e.containment == "document" ? document : window).width() - this.helperProportions.width - this.margins.left, (a(e.containment == "document" ? document : window).height() || doc(this).body.parentNode.scrollHeight) - this.helperProportions.height - this.margins.top]
            }
            if (!(/^(document|window|parent)$/).test(e.containment)) {
                var c = a(e.containment)[0];
                var d = a(e.containment).offset();
                var b = (a(c).css("overflow") != "hidden");
                this.containment = [d.left + (parseInt(a(c).css("borderLeftWidth"), 10) || 0) + (parseInt(a(c).css("paddingLeft"), 10) || 0) - this.margins.left, d.top + (parseInt(a(c).css("borderTopWidth"), 10) || 0) + (parseInt(a(c).css("paddingTop"), 10) || 0) - this.margins.top, d.left + (b ? Math.max(c.scrollWidth, c.offsetWidth) : c.offsetWidth) - (parseInt(a(c).css("borderLeftWidth"), 10) || 0) - (parseInt(a(c).css("paddingRight"), 10) || 0) - this.helperProportions.width - this.margins.left, d.top + (b ? Math.max(c.scrollHeight, c.offsetHeight) : c.offsetHeight) - (parseInt(a(c).css("borderTopWidth"), 10) || 0) - (parseInt(a(c).css("paddingBottom"), 10) || 0) - this.helperProportions.height - this.margins.top]
            }
        }, _convertPositionTo: function (f, h) {
            if (!h) {
                h = this.position
            }
            var c = f == "absolute" ? 1 : -1;
            var e = this.options,
                b = this.cssPosition == "absolute" && !(this.scrollParent[0] != document && a.ui.contains(this.scrollParent[0], this.offsetParent[0])) ? this.offsetParent : this.scrollParent,
                g = (/(html|body)/i).test(b[0].tagName);
            return {
                top: (h.top + this.offset.relative.top * c + this.offset.parent.top * c - (a.browser.safari && this.cssPosition == "fixed" ? 0 : (this.cssPosition == "fixed" ? -this.scrollParent.scrollTop() : (g ? 0 : b.scrollTop())) * c)),
                left: (h.left + this.offset.relative.left * c + this.offset.parent.left * c - (a.browser.safari && this.cssPosition == "fixed" ? 0 : (this.cssPosition == "fixed" ? -this.scrollParent.scrollLeft() : g ? 0 : b.scrollLeft()) * c))
            }
        }, _generatePosition: function (e) {
            var h = this.options,
                b = this.cssPosition == "absolute" && !(this.scrollParent[0] != document && a.ui.contains(this.scrollParent[0], this.offsetParent[0])) ? this.offsetParent : this.scrollParent,
                i = (/(html|body)/i).test(b[0].tagName);
            if (this.cssPosition == "relative" && !(this.scrollParent[0] != document && this.scrollParent[0] != this.offsetParent[0])) {
                this.offset.relative = this._getRelativeOffset()
            }
            var d = e.pageX;
            var c = e.pageY;
            if (this.originalPosition) {
                if (this.containment) {
                    if (e.pageX - this.offset.click.left < this.containment[0]) {
                        d = this.containment[0] + this.offset.click.left
                    }
                    if (e.pageY - this.offset.click.top < this.containment[1]) {
                        c = this.containment[1] + this.offset.click.top
                    }
                    if (e.pageX - this.offset.click.left > this.containment[2]) {
                        d = this.containment[2] + this.offset.click.left
                    }
                    if (e.pageY - this.offset.click.top > this.containment[3]) {
                        c = this.containment[3] + this.offset.click.top
                    }
                }
                if (h.grid) {
                    var g = this.originalPageY + Math.round((c - this.originalPageY) / h.grid[1]) * h.grid[1];
                    c = this.containment ? (!(g - this.offset.click.top < this.containment[1] || g - this.offset.click.top > this.containment[3]) ? g : (!(g - this.offset.click.top < this.containment[1]) ? g - h.grid[1] : g + h.grid[1])) : g;
                    var f = this.originalPageX + Math.round((d - this.originalPageX) / h.grid[0]) * h.grid[0];
                    d = this.containment ? (!(f - this.offset.click.left < this.containment[0] || f - this.offset.click.left > this.containment[2]) ? f : (!(f - this.offset.click.left < this.containment[0]) ? f - h.grid[0] : f + h.grid[0])) : f
                }
            }
            return {
                top: (c - this.offset.click.top - this.offset.relative.top - this.offset.parent.top + (a.browser.safari && this.cssPosition == "fixed" ? 0 : (this.cssPosition == "fixed" ? -this.scrollParent.scrollTop() : (i ? 0 : b.scrollTop())))),
                left: (d - this.offset.click.left - this.offset.relative.left - this.offset.parent.left + (a.browser.safari && this.cssPosition == "fixed" ? 0 : (this.cssPosition == "fixed" ? -this.scrollParent.scrollLeft() : i ? 0 : b.scrollLeft())))
            }
        }, _rearrange: function (g, f, c, e) {
            c ? c[0].appendChild(this.placeholder[0]) : f.item[0].parentNode.insertBefore(this.placeholder[0], (this.direction == "down" ? f.item[0] : f.item[0].nextSibling));
            this.counter = this.counter ? ++this.counter : 1;
            var d = this, b = this.counter;
            window.setTimeout(function () {
                if (b == d.counter) {
                    d.refreshPositions(!e)
                }
            }, 0)
        }, _clear: function (d, e) {
            this.reverting = false;
            var f = [], b = this;
            if (!this._noFinalSort && this.currentItem[0].parentNode) {
                this.placeholder.before(this.currentItem)
            }
            this._noFinalSort = null;
            if (this.helper[0] == this.currentItem[0]) {
                for (var c in this._storedCSS) {
                    if (this._storedCSS[c] == "auto" || this._storedCSS[c] == "static") {
                        this._storedCSS[c] = ""
                    }
                }
                this.currentItem.css(this._storedCSS).removeClass("ui-sortable-helper")
            } else {
                this.currentItem.show()
            }
            if (this.fromOutside && !e) {
                f.push(function (g) {
                    this._trigger("receive", g, this._uiHash(this.fromOutside))
                })
            }
            if ((this.fromOutside || this.domPosition.prev != this.currentItem.prev().not(".ui-sortable-helper")[0] || this.domPosition.parent != this.currentItem.parent()[0]) && !e) {
                f.push(function (g) {
                    this._trigger("update", g, this._uiHash())
                })
            }
            if (!a.ui.contains(this.element[0], this.currentItem[0])) {
                if (!e) {
                    f.push(function (g) {
                        this._trigger("remove", g, this._uiHash())
                    })
                }
                for (var c = this.containers.length - 1; c >= 0; c--) {
                    if (a.ui.contains(this.containers[c].element[0], this.currentItem[0]) && !e) {
                        f.push((function (g) {
                            return function (h) {
                                g._trigger("receive", h, this._uiHash(this))
                            }
                        }).call(this, this.containers[c]));
                        f.push((function (g) {
                            return function (h) {
                                g._trigger("update", h, this._uiHash(this))
                            }
                        }).call(this, this.containers[c]))
                    }
                }
            }
            for (var c = this.containers.length - 1; c >= 0; c--) {
                if (!e) {
                    f.push((function (g) {
                        return function (h) {
                            g._trigger("deactivate", h, this._uiHash(this))
                        }
                    }).call(this, this.containers[c]))
                }
                if (this.containers[c].containerCache.over) {
                    f.push((function (g) {
                        return function (h) {
                            g._trigger("out", h, this._uiHash(this))
                        }
                    }).call(this, this.containers[c]));
                    this.containers[c].containerCache.over = 0
                }
            }
            if (this._storedCursor) {
                a("body").css("cursor", this._storedCursor)
            }
            if (this._storedOpacity) {
                this.helper.css("opacity", this._storedOpacity)
            }
            if (this._storedZIndex) {
                this.helper.css("zIndex", this._storedZIndex == "auto" ? "" : this._storedZIndex)
            }
            this.dragging = false;
            if (this.cancelHelperRemoval) {
                if (!e) {
                    this._trigger("beforeStop", d, this._uiHash());
                    for (var c = 0; c < f.length; c++) {
                        f[c].call(this, d)
                    }
                    this._trigger("stop", d, this._uiHash())
                }
                return false
            }
            if (!e) {
                this._trigger("beforeStop", d, this._uiHash())
            }
            this.placeholder[0].parentNode.removeChild(this.placeholder[0]);
            if (this.helper[0] != this.currentItem[0]) {
                this.helper.remove()
            }
            this.helper = null;
            if (!e) {
                for (var c = 0; c < f.length; c++) {
                    f[c].call(this, d)
                }
                this._trigger("stop", d, this._uiHash())
            }
            this.fromOutside = false;
            return true
        }, _trigger: function () {
            if (a.widget.prototype._trigger.apply(this, arguments) === false) {
                this.cancel()
            }
        }, _uiHash: function (c) {
            var b = c || this;
            return {
                helper: b.helper,
                placeholder: b.placeholder || a([]),
                position: b.position,
                absolutePosition: b.positionAbs,
                offset: b.positionAbs,
                item: b.currentItem,
                sender: c ? c.element : null
            }
        }
    }));
    a.extend(a.ui.sortable, {
        getter: "serialize toArray",
        version: "1.7.2",
        eventPrefix: "sort",
        defaults: {
            appendTo: "parent",
            axis: false,
            cancel: ":input,option",
            connectWith: false,
            containment: false,
            cursor: "auto",
            cursorAt: false,
            delay: 0,
            distance: 1,
            dropOnEmpty: true,
            forcePlaceholderSize: false,
            forceHelperSize: false,
            grid: false,
            handle: false,
            helper: "original",
            items: "> *",
            opacity: false,
            placeholder: false,
            revert: false,
            scroll: true,
            scrollSensitivity: 20,
            scrollSpeed: 20,
            scope: "default",
            tolerance: "intersect",
            zIndex: 1000
        }
    })
})(jQuery);
;
//Added for iframe compatibility
doc = function (t) {
    var d = $(t.element)[0] || $(t)[0];
    d = d.URL || d.ownerDocument.URL;
    if (d != document.URL) return ifrDoc;
    else return document;
}