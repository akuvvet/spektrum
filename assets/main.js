/* Nur echte Funktionen: mobiles Menue, Fehlermeldung, Bot-Zeitstempel,
   Einblenden beim Scrollen. Ohne JavaScript bleibt die Seite vollstaendig nutzbar. */
(function () {
  "use strict";

  var schmalAbfrage = window.matchMedia("(max-width: 61.999rem)");
  var schalter = document.querySelector(".nav__schalter");
  var nav = document.getElementById("hauptnavigation");

  if (schalter && nav) {
    if (schmalAbfrage.matches) { nav.hidden = true; }
    schalter.addEventListener("click", function () {
      var offen = nav.hidden;
      nav.hidden = !offen;
      schalter.setAttribute("aria-expanded", offen ? "true" : "false");
    });
    window.addEventListener("resize", function () {
      if (!schmalAbfrage.matches) {
        nav.hidden = false;
        schalter.setAttribute("aria-expanded", "false");
      } else if (schalter.getAttribute("aria-expanded") !== "true") {
        nav.hidden = true;
      }
    });
  }

  var box = document.getElementById("fehlerbox");
  if (box) {
    var meldungen = {
      "1": "Die Angaben waren unvollst\u00e4ndig oder die E-Mail-Adresse war ung\u00fcltig. Bitte pr\u00fcfen Sie die Pflichtfelder und die Zustimmung zur Datenschutzerkl\u00e4rung.",
      "2": "Es wurden zu viele Nachrichten in kurzer Zeit gesendet. Bitte versuchen Sie es in einer Stunde erneut oder rufen Sie uns an: 0212 3820752.",
      "3": "Die Nachricht konnte technisch nicht versendet werden. Bitte schreiben Sie an info@spektrum-ev.de oder rufen Sie an: 0212 3820752."
    };
    var code = new URLSearchParams(window.location.search).get("fehler");
    if (code && Object.prototype.hasOwnProperty.call(meldungen, code)) {
      document.getElementById("fehlertext").textContent = meldungen[code];
      box.hidden = false;
      box.scrollIntoView({ block: "center" });
    }
  }

  var ts = document.getElementById("ts");
  if (ts) { ts.value = String(Math.floor(Date.now() / 1000)); }

  /* ------------------------------------------- Projektfenster
     Ohne JavaScript oeffnet und schliesst schon die Adresszeile (:target).
     Hier kommt dazu: Escape, Klick auf den Grund, kein Mitscrollen der
     Seite dahinter, und der Fokus wandert ins Fenster und wieder zurueck. */
  var fenster = Array.prototype.slice.call(document.querySelectorAll(".fenster"));
  if (fenster.length) {
    var herkunft = null;

    function fensterZeigen(f, ausloeser) {
      fenster.forEach(function (a) { a.classList.remove("fenster--offen"); });
      f.classList.add("fenster--offen");
      document.documentElement.classList.add("starr");
      herkunft = ausloeser || null;
      var zu = f.querySelector(".fenster__zu");
      if (zu) { zu.focus(); }
    }

    function fensterSchliessen() {
      var offen = document.querySelector(".fenster--offen");
      if (offen) { offen.classList.remove("fenster--offen"); }
      document.documentElement.classList.remove("starr");
      if (location.hash.indexOf("#p-") === 0) {
        history.replaceState(null, "", location.pathname + location.search);
      }
      if (herkunft) { herkunft.focus(); herkunft = null; }
    }

    // Klick auf eine Kachel
    document.querySelectorAll('a[href^="#p-"]').forEach(function (a) {
      a.addEventListener("click", function (e) {
        var ziel = document.getElementById(a.getAttribute("href").slice(1));
        if (!ziel) { return; }
        e.preventDefault();
        history.replaceState(null, "", a.getAttribute("href"));
        fensterZeigen(ziel, a);
      });
    });

    // Schliesser und Grund
    document.querySelectorAll('.fenster__zu, .fenster__grund').forEach(function (a) {
      a.addEventListener("click", function (e) { e.preventDefault(); fensterSchliessen(); });
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && document.querySelector(".fenster--offen")) {
        fensterSchliessen();
      }
    });

    // Der Fokus bleibt im offenen Fenster
    document.addEventListener("focusin", function (e) {
      var offen = document.querySelector(".fenster--offen");
      if (offen && !offen.contains(e.target)) {
        var zu = offen.querySelector(".fenster__zu");
        if (zu) { zu.focus(); }
      }
    });

    // Direkt aufgerufene Adresse wie .../projekte.html#p-xyz
    if (location.hash.indexOf("#p-") === 0) {
      var start = document.getElementById(location.hash.slice(1));
      if (start) { fensterZeigen(start, null); }
    }

    /* ---- Lupe: ein Bild gross ueber allem ---- */
    var lupe = document.createElement("div");
    lupe.className = "lupe";
    lupe.hidden = true;
    lupe.setAttribute("role", "dialog");
    lupe.setAttribute("aria-modal", "true");
    lupe.setAttribute("aria-label", "Bild in voller Größe");
    var lupeBild = document.createElement("img");
    lupeBild.alt = "";
    var lupeText = document.createElement("p");
    lupeText.className = "lupe__text";
    var lupeZu = document.createElement("button");
    lupeZu.type = "button";
    lupeZu.className = "lupe__zu";
    lupeZu.setAttribute("aria-label", "Bild schließen");
    lupeZu.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
      + '<path d="M6 6 L18 18 M18 6 L6 18"/></svg>';
    lupe.appendChild(lupeBild);
    lupe.appendChild(lupeText);
    lupe.appendChild(lupeZu);
    document.body.appendChild(lupe);

    var lupeHerkunft = null;
    function lupeSchliessen() {
      lupe.hidden = true;
      lupeBild.removeAttribute("src");
      if (lupeHerkunft) { lupeHerkunft.focus(); lupeHerkunft = null; }
    }
    lupeZu.addEventListener("click", lupeSchliessen);
    lupe.addEventListener("click", function (e) {
      if (e.target === lupe || e.target === lupeText) { lupeSchliessen(); }
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !lupe.hidden) { e.stopPropagation(); lupeSchliessen(); }
    }, true);

    document.querySelectorAll(".galerie__gross").forEach(function (a) {
      a.addEventListener("click", function (e) {
        e.preventDefault();
        var bild = a.querySelector("img");
        lupeBild.src = a.getAttribute("href");
        lupeBild.alt = bild ? bild.alt : "";
        var unter = a.parentNode.querySelector("figcaption");
        lupeText.textContent = unter ? unter.textContent : "";
        lupe.hidden = false;
        lupeHerkunft = a;
        lupeZu.focus();
      });
    });
  }

  /* ---------------------------------------------- Bildbanner */
  var schau = document.querySelector(".hero--banner");
  if (schau) {
    var dias = Array.prototype.slice.call(schau.querySelectorAll(".banner__dia"));
    var texte = Array.prototype.slice.call(schau.querySelectorAll(".hero__text"));
    var punkte = Array.prototype.slice.call(schau.querySelectorAll(".banner__punkt"));
    var jetzt = 0;
    var uhr = null;
    var WECHSEL = 8500;
    var ruhig = window.matchMedia("(prefers-reduced-motion: reduce)");

    function zeige(nr) {
      jetzt = (nr + dias.length) % dias.length;
      dias.forEach(function (d, i) {
        d.classList.toggle("banner__dia--aktiv", i === jetzt);
        if (i === jetzt) { d.removeAttribute("aria-hidden"); }
        else { d.setAttribute("aria-hidden", "true"); }
      });
      // Der Text wechselt mit dem Bild — Folie und Aussage gehoeren zusammen
      texte.forEach(function (t, i) {
        t.classList.toggle("hero__text--aktiv", i === jetzt);
        if (i === jetzt) { t.removeAttribute("aria-hidden"); }
        else { t.setAttribute("aria-hidden", "true"); }
      });
      punkte.forEach(function (k, i) {
        if (i === jetzt) { k.setAttribute("aria-current", "true"); }
        else { k.removeAttribute("aria-current"); }
      });
    }

    function starten() {
      if (uhr || ruhig.matches || dias.length < 2) { return; }
      uhr = window.setInterval(function () { zeige(jetzt + 1); }, WECHSEL);
    }
    function anhalten() {
      if (uhr) { window.clearInterval(uhr); uhr = null; }
    }
    function vonHand(nr) { anhalten(); zeige(nr); starten(); }

    schau.querySelector(".banner__pfeil--vor")
      .addEventListener("click", function () { vonHand(jetzt + 1); });
    schau.querySelector(".banner__pfeil--zurueck")
      .addEventListener("click", function () { vonHand(jetzt - 1); });
    punkte.forEach(function (k) {
      k.addEventListener("click", function () { vonHand(parseInt(k.dataset.zu, 10)); });
    });

    // Pfeiltasten, wenn die Bildschau den Fokus hat
    schau.addEventListener("keydown", function (e) {
      if (e.key === "ArrowRight") { e.preventDefault(); vonHand(jetzt + 1); }
      if (e.key === "ArrowLeft") { e.preventDefault(); vonHand(jetzt - 1); }
    });

    // Endlosschleife: die Maus haelt den Wechsel nicht mehr an — sonst
    // steht die Schau still, sobald der Zeiger zufaellig darauf liegt.
    // Nur wer wirklich bedient (Tastaturfokus) oder den Tab wechselt,
    // haelt sie an; danach laeuft sie von allein weiter.
    schau.addEventListener("focusin", anhalten);
    schau.addEventListener("focusout", starten);
    document.addEventListener("visibilitychange", function () {
      if (document.hidden) { anhalten(); } else { starten(); }
    });
    if (ruhig.addEventListener) {
      ruhig.addEventListener("change", function () { anhalten(); starten(); });
    }

    starten();
  }

  /* Einblenden beim Scrollen */
  var teile = document.querySelectorAll(".an");
  var ruhig = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  if (ruhig || !("IntersectionObserver" in window)) {
    for (var i = 0; i < teile.length; i++) { teile[i].classList.add("sichtbar"); }
    return;
  }

  var beobachter = new IntersectionObserver(function (eintraege) {
    eintraege.forEach(function (e) {
      if (e.isIntersecting) {
        e.target.classList.add("sichtbar");
        beobachter.unobserve(e.target);
      }
    });
  }, { rootMargin: "0px 0px -8% 0px", threshold: 0.06 });

  for (var j = 0; j < teile.length; j++) {
    var kasten = teile[j].getBoundingClientRect();
    if (kasten.top < window.innerHeight) {
      teile[j].classList.add("sichtbar");   // schon sichtbar: nicht erst ausblenden
    } else {
      beobachter.observe(teile[j]);
    }
  }
})();

/* ----------------------------------------------------------------------
   Laufleiste der Kooperationspartner

   Das Band ist ein gewoehnlicher Scrollbereich — Finger, Trackpad und
   Pfeiltasten funktionieren deshalb auch ohne diese Datei. Hier kommen
   zwei Dinge dazu: der langsame Selbstlauf und das Ziehen mit der Maus.

   Die zweite, fuer Vorleseprogramme verdeckte Spur macht den Uebergang
   nahtlos: ist die erste Spur durchgelaufen, springt die Anzeige um genau
   deren Breite zurueck. Zu sehen ist davon nichts.
   ---------------------------------------------------------------------- */
(function () {
  "use strict";

  var band = document.querySelector(".partner__band");
  var lauf = band && band.querySelector(".partner__lauf");
  if (!band || !lauf) { return; }

  var TEMPO = 24;                 /* Bildpunkte je Sekunde — ruhiges Tempo */
  var haelfte = 0;                /* Breite einer Spur */
  var ruhig = window.matchMedia("(prefers-reduced-motion: reduce)");
  var pause = false;              /* Mauszeiger darueber oder Tastaturfokus */
  var zieht = false;
  var startX = 0, startLinks = 0, zeiger = null;
  var zuletzt = 0;
  var stand = 0;                  /* eigene Rechnung mit Nachkommastellen */
  var geschrieben = -1;           /* was wir zuletzt selbst gesetzt haben */
  var ruheBis = 0;                /* nach eigenem Verschieben kurz stillhalten */

  function messen() { haelfte = lauf.scrollWidth / 2; }
  messen();
  window.addEventListener("resize", messen);
  window.addEventListener("load", messen);   /* Bilder sind erst dann vermessen */
  if (window.ResizeObserver) { new ResizeObserver(messen).observe(lauf); }

  function schritt(zeit) {
    if (!zuletzt) { zuletzt = zeit; }
    var d = (zeit - zuletzt) / 1000;
    zuletzt = zeit;
    if (d > 0.25) { d = 0.25; }   /* nach einem Tabwechsel nicht springen */

    if (!pause && !zieht && zeit > ruheBis && !ruhig.matches && !document.hidden) {
      /* scrollLeft gibt beim Auslesen ganze Zahlen zurueck. Wuerde man
         direkt darauf rechnen, gingen die 0,4 Bildpunkte je Bild jedes
         Mal verloren und die Leiste stuende still. Deshalb die eigene
         Rechnung in "stand". */
      stand += TEMPO * d;
      if (haelfte > 0 && stand >= haelfte) { stand -= haelfte; }
      band.scrollLeft = stand;
      geschrieben = band.scrollLeft;
    } else {
      stand = band.scrollLeft;    /* jemand hat selbst verschoben */
      geschrieben = stand;
    }
    window.requestAnimationFrame(schritt);
  }
  window.requestAnimationFrame(schritt);

  /* Wischen auf dem Telefon und Rollen am Trackpad loesen kein mouseenter
     aus. Verschiebt sich das Band also ohne unser Zutun, uebernehmen wir
     den neuen Stand und halten zweieinhalb Sekunden still — sonst zoege
     der Selbstlauf die Leiste unter dem Finger wieder weg. */
  band.addEventListener("scroll", function () {
    if (zieht) { return; }
    if (Math.abs(band.scrollLeft - geschrieben) > 2) {
      stand = band.scrollLeft;
      geschrieben = stand;
      ruheBis = (window.performance ? performance.now() : Date.now()) + 2500;
    }
  }, { passive: true });

  /* Anhalten, solange jemand hinsieht oder mit der Tastatur darin ist. */
  band.addEventListener("mouseenter", function () { pause = true; });
  band.addEventListener("mouseleave", function () { pause = false; });
  band.addEventListener("focusin", function () { pause = true; });
  band.addEventListener("focusout", function () { pause = false; });

  /* Ziehen mit der Maus. Finger und Trackpad brauchen das nicht — die
     scrollen den Bereich von sich aus und kaemen sich hier nur ins Gehege.
     Beim Ueberlaufen wird der Startpunkt mitverschoben, damit es sich in
     beide Richtungen endlos anfuehlt. */
  function verschieben(dx) {
    var neu = startLinks - dx;
    if (haelfte > 0) {
      while (neu < 0) { neu += haelfte; startLinks += haelfte; }
      while (neu >= haelfte) { neu -= haelfte; startLinks -= haelfte; }
    } else if (neu < 0) {
      neu = 0;
    }
    band.scrollLeft = neu;
  }

  band.addEventListener("pointerdown", function (e) {
    if (e.pointerType !== "mouse" || e.button !== 0) { return; }
    zieht = true;
    startX = e.clientX;
    startLinks = band.scrollLeft;
    zeiger = e.pointerId;
    try { band.setPointerCapture(zeiger); } catch (x) {}
    band.classList.add("partner__band--zieht");
  });

  band.addEventListener("pointermove", function (e) {
    if (!zieht || e.pointerId !== zeiger) { return; }
    e.preventDefault();
    verschieben(e.clientX - startX);
  });

  function loslassen() {
    if (!zieht) { return; }
    zieht = false;
    band.classList.remove("partner__band--zieht");
    try { band.releasePointerCapture(zeiger); } catch (x) {}
  }
  band.addEventListener("pointerup", loslassen);
  band.addEventListener("pointercancel", loslassen);
  band.addEventListener("lostpointercapture", loslassen);

  /* Sonst startet das Ziehen ueber einem Zeichen das Bild-Verschieben des Browsers. */
  band.addEventListener("dragstart", function (e) { e.preventDefault(); });
})();
