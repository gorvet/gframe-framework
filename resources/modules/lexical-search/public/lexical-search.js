(function (window) {
  "use strict";

  var stopWords = ["de", "del", "la", "las", "el", "los", "en", "por", "para", "con", "sin", "un", "una", "al", "y", "o"];

  function normalize(value) {
    return String(value || "")
      .toLocaleLowerCase("es")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/[^a-z0-9ñ\s]+/gi, " ")
      .replace(/\s+/g, " ")
      .trim();
  }

  function tokens(value) {
    return normalize(value).split(" ").filter(function (token, index, list) {
      return token.length >= 2 && stopWords.indexOf(token) === -1 && list.indexOf(token) === index;
    }).slice(0, 10);
  }

  function distance(left, right) {
    var previous = [];
    var current = [];
    var i;
    var j;
    for (j = 0; j <= right.length; j += 1) previous[j] = j;
    for (i = 1; i <= left.length; i += 1) {
      current = [i];
      for (j = 1; j <= right.length; j += 1) {
        current[j] = Math.min(
          current[j - 1] + 1,
          previous[j] + 1,
          previous[j - 1] + (left.charAt(i - 1) === right.charAt(j - 1) ? 0 : 1)
        );
      }
      previous = current;
    }
    return previous[right.length];
  }

  function tokenScore(token, normalizedText, words) {
    var best = 0;
    if (normalizedText.indexOf(token) !== -1) return 1;
    if (token.length < 4) return 0;

    words.forEach(function (word) {
      var limit;
      if (word.indexOf(token) === 0 || token.indexOf(word) === 0) {
        best = Math.max(best, 0.82);
        return;
      }
      limit = token.length >= 7 ? 2 : 1;
      if (Math.abs(word.length - token.length) <= limit && distance(token, word) <= limit) {
        best = Math.max(best, 0.68);
      }
    });
    return best;
  }

  function score(query, text) {
    var queryTokens = tokens(query);
    var normalizedText = normalize(text);
    var words = normalizedText.split(" ");
    var total = 0;
    if (queryTokens.length === 0) return 1;
    queryTokens.forEach(function (token) {
      total += tokenScore(token, normalizedText, words);
    });
    return total / queryTokens.length;
  }

  window.GFrameLexicalSearch = {
    normalize: normalize,
    score: score,
    matches: function (query, text) {
      return normalize(query) === "" || score(query, text) >= 0.62;
    }
  };
})(window);
