!(function ($) {
  "use strict";

  if (window.GFHeartbeat) return;

  var BEAT_MS = 60000;
  var HIDDEN_MULT = 5;
  var LEADER_TTL_MS = 30000;
  var LEADER_RENEW_MS = 10000;

  function buildScopeId() {
    var rawBase = String((typeof site_url !== "undefined" && site_url) ? site_url : (window.location.origin + "/"));
    var normalized = rawBase.replace(/^https?:\/\//i, "").replace(/\/+$/g, "").toLowerCase();
    var safe = normalized.replace(/[^a-z0-9]+/g, "_").replace(/^_+|_+$/g, "");
    return safe || "default";
  }

  var SCOPE_ID = buildScopeId();
  var LEADER_KEY = "__gf_hb_leader_" + SCOPE_ID;
  var RELAY_KEY = "__gf_hb_relay_" + SCOPE_ID;
  var BUS_NAME = "gf_heartbeat_bus_" + SCOPE_ID;

  var TAB_ID = "tab_" + Math.random().toString(36).slice(2) + "_" + Date.now();
  var inFlight = false;
  var authInactive = false;
  var activeRequest = null;
  var lastTickAt = 0;
  var lastResumeTickAt = 0;
  var hiddenSkips = 0;
  var leadershipTimer = null;
  var beatTimer = null;

  var channel = ("BroadcastChannel" in window) ? new BroadcastChannel(BUS_NAME) : null;

  function isProtected() {
    if (authInactive || window.__gfAuthInactive === true) return false;
    return (typeof is_protected !== "undefined") ? !!is_protected : false;
  }

  function isVisible() {
    return document.visibilityState === "visible";
  }

  function nowMs() {
    return Date.now();
  }

  function csrf() {
    var $tokens = $("#tokens");
    var tk = $tokens.find('[name="csrfToken"]').val() || "";
    var ts = $tokens.find('[name="csrfTimestamp"]').val() || "";
    return { csrfToken: tk, csrfTimestamp: ts };
  }

  function dispatchEvent(name, detail) {
    document.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
  }

  function safeParse(str) {
    try {
      return JSON.parse(String(str || ""));
    } catch (e) {
      return null;
    }
  }

  function readLeader() {
    return safeParse(localStorage.getItem(LEADER_KEY)) || {};
  }

  function writeLeader(obj) {
    localStorage.setItem(LEADER_KEY, JSON.stringify(obj || {}));
  }

  function isLeaderTab() {
    var leader = readLeader();
    return !!leader && leader.id === TAB_ID;
  }

  function leaderExpired(leader) {
    var ts = (leader && leader.ts) ? parseInt(leader.ts, 10) : 0;
    if (!ts) return true;
    return (nowMs() - ts) > LEADER_TTL_MS;
  }

  function claimLeadership() {
    writeLeader({ id: TAB_ID, ts: nowMs() });
  }

  function renewLeadership() {
    if (!isLeaderTab()) return;
    writeLeader({ id: TAB_ID, ts: nowMs() });
  }

  function evaluateLeadership(allowTakeover) {
    if (!isProtected()) return;
    var leader = readLeader();
    if (!leader.id || leaderExpired(leader)) {
      claimLeadership();
      return;
    }

    // Solo toma liderazgo cuando esta pestaña entra a foco/visible.
    if (!!allowTakeover && isVisible() && leader.id !== TAB_ID) {
      claimLeadership();
    }
  }

  function emitPayload(res) {
    if (res && res.code === "expired") {
      enterAuthInactive("expired");
      dispatchEvent("gf:heartbeat:expired", { response: res });
      return;
    }

    if (authInactive || window.__gfAuthInactive === true) {
      return;
    }

    var bucket = (res && res.data && res.data.channels) ? res.data.channels : {};
    var keys = Object.keys(bucket);
    for (var i = 0; i < keys.length; i++) {
      var key = keys[i];
      dispatchEvent("gf:heartbeat:" + key, {
        channel: key,
        payload: bucket[key] || {},
        response: res || {}
      });
    }

    dispatchEvent("gf:heartbeat:tick", { response: res || {} });
  }

  function responseLooksExpired(xhr, status) {
    var httpStatus = xhr && xhr.status ? parseInt(xhr.status, 10) : 0;
    if (httpStatus === 401 || httpStatus === 403) return true;

    var contentType = String((xhr && xhr.getResponseHeader) ? (xhr.getResponseHeader("content-type") || "") : "").toLowerCase();
    var body = String((xhr && xhr.responseText) ? xhr.responseText : "").toLowerCase();

    return status === "parsererror"
      && contentType.indexOf("text/html") !== -1
      && (body.indexOf("403") !== -1 || body.indexOf("sesion") !== -1 || body.indexOf("session") !== -1);
  }

  function postBus(message) {
    if (!message) return;
    if (channel) {
      channel.postMessage(message);
    }
    localStorage.setItem(RELAY_KEY, JSON.stringify(message));
  }

  function requestLeaderTick() {
    if (!isProtected()) return;
    if (isLeaderTab()) {
      tick(true);
      return;
    }
    postBus({ type: "trigger", sender: TAB_ID, at: nowMs() });
  }

  function requestLeaderTickOnResume() {
    if (!isProtected()) return;
    var now = nowMs();
    if ((now - lastResumeTickAt) < 1200) return;
    lastResumeTickAt = now;
    requestLeaderTick();
  }

  function handleBusMessage(msg) {
    if (!msg || msg.sender === TAB_ID) return;

    if (msg.type === "result") {
      emitPayload(msg.res || {});
      return;
    }

    if (authInactive || window.__gfAuthInactive === true) return;

    if (msg.type === "trigger" && isLeaderTab()) {
      tick(true);
    }
  }

  function tick(force) {
    if (!isProtected()) return;
    if (!isLeaderTab()) return;
    if (inFlight) return;

    var now = nowMs();
    // Anti-rafaga interna fija (no configurable por canal).
    var minGap = force ? 300 : 15000;
    if ((now - lastTickAt) < minGap) return;
    lastTickAt = now;

    var postData = $.extend({}, csrf(), {
      hb_visible: isVisible() ? 1 : 0,
      hb_force: force ? 1 : 0
    });

    inFlight = true;
    activeRequest = $.post(site_url + "ajax/heartbeat", postData, function (res) {
      inFlight = false;
      activeRequest = null;
      emitPayload(res || {});
      postBus({ type: "result", sender: TAB_ID, at: nowMs(), res: (res || {}) });
    }, "json").fail(function (xhr, status, err) {
      inFlight = false;
      activeRequest = null;
      if (status === "abort" || !isProtected()) return;
      if (responseLooksExpired(xhr, status)) {
        var expiredRes = { status: "unauthorized", code: "expired" };
        enterAuthInactive("expired");
        dispatchEvent("gf:heartbeat:expired", { response: expiredRes });
        postBus({ type: "result", sender: TAB_ID, at: nowMs(), res: expiredRes });
        return;
      }
      dispatchEvent("gf:heartbeat:error", { xhr: xhr, status: status, error: err });
    });
  }

  function enterAuthInactive(reason) {
    if (authInactive) return;
    authInactive = true;
    window.__gfAuthInactive = true;
    inFlight = false;

    if (activeRequest && typeof activeRequest.abort === "function") {
      activeRequest.abort();
    }
    activeRequest = null;

    if (leadershipTimer) {
      clearInterval(leadershipTimer);
      leadershipTimer = null;
    }
    if (beatTimer) {
      clearInterval(beatTimer);
      beatTimer = null;
    }
    if (isLeaderTab()) {
      localStorage.removeItem(LEADER_KEY);
    }

    if (reason === "logout") {
      dispatchEvent("gf:heartbeat:logout", { reason: "logout" });
    }
  }

  if (channel) {
    channel.onmessage = function (e) {
      handleBusMessage(e ? e.data : null);
    };
  }

  window.addEventListener("storage", function (event) {
    if (event.key === "session_closed_" + SCOPE_ID || event.key === "session_expired_" + SCOPE_ID) {
      enterAuthInactive("logout");
      return;
    }

    if (event.key === RELAY_KEY && event.newValue) {
      handleBusMessage(safeParse(event.newValue));
      return;
    }

    if (event.key === LEADER_KEY) {
      evaluateLeadership(false);
    }
  });

  window.addEventListener("beforeunload", function () {
    if (isLeaderTab()) {
      localStorage.removeItem(LEADER_KEY);
    }
  });

  document.addEventListener("visibilitychange", function () {
    if (!isProtected()) return;
    evaluateLeadership(true);
    if (isVisible()) {
      requestLeaderTickOnResume();
    }
  });

  window.addEventListener("focus", function () {
    if (!isProtected()) return;
    evaluateLeadership(true);
    if (isVisible()) {
      requestLeaderTickOnResume();
    }
  });

  window.GFHeartbeat = {
    triggerNow: function () {
      if (!isProtected()) return;
      evaluateLeadership(true);
      requestLeaderTick();
    },
    stop: function (reason) {
      enterAuthInactive(reason || "logout");
    },
    authInactive: function () {
      enterAuthInactive("logout");
    }
  };

  if (isProtected()) {
    evaluateLeadership(true);

    leadershipTimer = setInterval(function () {
      evaluateLeadership(false);
      renewLeadership();
    }, LEADER_RENEW_MS);

    beatTimer = setInterval(function () {
      if (!isLeaderTab()) return;

      if (isVisible()) {
        hiddenSkips = 0;
        tick(false);
      } else {
        hiddenSkips++;
        if (hiddenSkips % HIDDEN_MULT === 0) {
          tick(false);
        }
      }
    }, BEAT_MS);

    requestLeaderTick();
  }
})(jQuery);
