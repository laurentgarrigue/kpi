// Shared pieces of the mockups: site chrome, competition and event headers, tabs, demo bar, game blocks.
const KPI_IMG = 'https://kpi.localhost/img/';
const KPI_MEDIA = 'https://kpi.localhost';
const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const decode = s => { const t = document.createElement('textarea'); t.innerHTML = s ?? ''; return t.value; };
const cap = s => s.charAt(0).toUpperCase() + s.slice(1);
const utc = d => new Date(d + 'T00:00:00Z');
const fmt = (d, o) => new Intl.DateTimeFormat('fr-FR', { timeZone: 'UTC', ...o }).format(utc(d));
const arrowIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';

function dateRange(start, end) {
  const s = utc(start), e = utc(end);
  if (start === end) return fmt(start, { day: 'numeric', month: 'long' });
  if (s.getUTCMonth() === e.getUTCMonth()) return `${s.getUTCDate()}–${fmt(end, { day: 'numeric', month: 'long' })}`;
  return `${fmt(start, { day: 'numeric', month: 'long' })} – ${fmt(end, { day: 'numeric', month: 'long' })}`;
}
function shortRange(s, e) {
  const m = d => fmt(d, { month: 'short' });
  if (s === e) return `${+s.slice(8)} ${m(s)}`;
  return s.slice(5, 7) === e.slice(5, 7) ? `${+s.slice(8)}–${+e.slice(8)} ${m(e)}` : `${+s.slice(8)} ${m(s)} – ${+e.slice(8)} ${m(e)}`;
}
const place = x => [x.place, x.department && /^\d+$/.test(x.department) ? `(${x.department})` : x.department].filter(Boolean).join(' ');

const MENU = [['Accueil', 'accueil.html'], ['Actualités', 'actualites.html'], ['Calendrier', 'calendrier.html'], ['Événements', 'evenements.html'],
  ['Compétitions', 'competitions.html'], ['Clubs et équipes', 'clubs.html'], ['Le kayak-polo', 'contenu.html'], ['En direct', '#']];
function siteChrome(current) {
  return `<div class="beta"><div class="wrap">Version bêta du nouveau site kayak-polo.info. Le site actuel reste disponible : <a href="#">accéder au site actuel</a></div></div>
  <div class="wrap masthead"><img src="/img/brand/ffck.png" alt="FFCK"><div><b>kayak-polo.info</b><small>Commission Nationale d'Activité Kayak-Polo</small></div>
    <a href="recherche.html" style="margin-left:auto" aria-label="Rechercher"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></a></div>
  <nav class="mainnav" aria-label="Menu principal"><div class="wrap">${MENU.map(([l, h]) => `<a href="${h}"${l === current ? ' aria-current="page"' : ''}>${l}</a>`).join('')}</div></nav>`;
}

const COMPETITION_TABS = [['Infos', 'infos.html'], ['Matchs', 'matchs.html'], ['Terrains', 'terrains.html'], ['Déroulement', 'deroulement.html'], ['Classement', 'classement.html'], ['Stats', 'stats.html']];
const tabsBar = (tabs, current, label) => `<div class="bar"><nav class="tabs" aria-label="${label}">${tabs.map(([l, h]) => `<a href="${h}"${l === current ? ' aria-current="page"' : ''}>${l}</a>`).join('')}</nav></div>`;

/** The event a competition belongs to, as an invitation: the other competitions, all the games, the live. */
function eventCta(ev, competitionCount) {
  const s = utc(ev.start), e = utc(ev.end);
  const days = ev.start === ev.end ? `${s.getUTCDate()}` : `${s.getUTCDate()}–${e.getUTCDate()}`;
  const others = competitionCount > 1 ? `Les ${competitionCount} compétitions, tous les matchs par terrain et le direct` : 'Tous les matchs par terrain et le direct';
  return `<a class="event-cta" href="evenement.html">
    <span class="tile" aria-hidden="true">${days}<small>${fmt(ev.end, { month: 'short' })}</small></span>
    <span><b>${esc(ev.libelle)}</b><span class="sub">${esc(ev.place)}, ${dateRange(ev.start, ev.end)}. ${others}.</span></span>
    <span class="go">Voir l'événement ${arrowIcon}</span></a>`;
}

function competitionHeader(c, tab, eventCompetitionCount = 0) {
  const ev = c.events?.[0];
  const state = { END: 'Terminé', ON: 'En cours', ATT: 'À venir' }[c.status] ?? '';
  return `<div class="wrap">
    <nav class="crumbs" aria-label="Fil d'Ariane"><a href="accueil.html">Accueil</a> › <a href="competitions.html">Compétitions</a> › ${esc(c.display_title)}</nav>
    <header>
      <div class="title-row"><h1>${esc(c.display_title)}</h1><span class="season">${esc(c.season)}</span><span class="state">${state}</span><a class="live-btn" href="#">Suivre en direct</a></div>
      ${(c.siblings ?? []).length > 1 ? `<nav class="siblings" aria-label="Compétitions du groupe ${esc(c.group?.libelle)}">${c.siblings.map(s => `<a href="#"${s.code === c.code ? ' aria-current="page"' : ''}>${esc(s.soustitre2 || s.display_title)}</a>`).join('')}</nav>` : ''}
      ${ev ? eventCta(ev, eventCompetitionCount) : ''}
    </header>
    ${tabsBar(COMPETITION_TABS, tab, 'Sections de la compétition')}
  </div>`;
}

const EVENT_TABS = [['Compétitions', 'evenement.html'], ['Matchs', 'matchs.html'], ['Terrains', 'terrains.html'], ['Déroulement', 'deroulement.html'], ['Classement', 'classement.html'], ['Stats', 'stats.html']];
function eventHeader(ev, competitions, tab) {
  return `<div class="event-band"><div class="wrap">
    <nav class="crumbs" aria-label="Fil d'Ariane"><a href="accueil.html">Accueil</a> › <a href="evenements.html">Événements</a> › ${esc(ev.libelle)}</nav>
    <div class="grow"><h1>${esc(ev.libelle)}</h1><p class="where">${esc(ev.place)}, ${dateRange(ev.start, ev.end)} ${ev.end.slice(0, 4)}</p></div>
    <p class="count">${competitions}<small>compétitions</small></p>
    <a class="live-btn" href="#">Suivre en direct</a>
  </div></div>
  <div class="wrap">${tabsBar(EVENT_TABS, tab, "Sections de l'événement")}</div>`;
}

function demoBar(extra = '') {
  const bar = document.createElement('div');
  bar.className = 'demo';
  bar.innerHTML = `<a href="index.html">Toutes les maquettes</a> ${extra} <button type="button" data-theme-toggle>Thème sombre</button>`;
  document.body.append(bar);
  bar.querySelector('[data-theme-toggle]').addEventListener('click', () => {
    const r = document.documentElement;
    r.dataset.theme = r.dataset.theme === 'dark' ? 'light' : 'dark';
  });
  return bar;
}

/** Logo of each team, by team number, from the games (the info and ranking payloads have none yet). */
function logosByTeam(games) {
  const map = {};
  for (const g of games) {
    if (g.t_a_logo) map[g.t_a_number] = g.t_a_logo;
    if (g.t_b_logo) map[g.t_b_number] = g.t_b_logo;
  }
  return map;
}
const logoImg = path => path && !/empty-logo/.test(path) ? `<img class="logo" src="${KPI_IMG}${path}" alt="" loading="lazy" onerror="this.style.visibility='hidden'">` : '<span class="logo"></span>';

/** Waiting labels such as « (Winner game #236) » read as « Vainqueur n° 236 ». */
function slotLabel(label) {
  const m = label?.match(/\((Winner|Vainqueur|Loser|Perdant)\s+(?:game|match)\s*#?(\d+)\)/i);
  if (m) return `${/^(winner|vainqueur)/i.test(m[1]) ? 'Vainqueur' : 'Perdant'} n° ${m[2]}`;
  return label?.replace(/^\((.*)\)\s*$/, '$1') ?? 'À définir';
}

function duelHtml(g, { when = true, teamAttr = false } = {}) {
  const has = g.g_status !== 'ATT' && g.g_score_a !== null;
  const a = +g.g_score_a, b = +g.g_score_b;
  const side = (s, win) => {
    const n = g[`t_${s}_number`], label = g[`t_${s}_label`];
    const tbd = n === null || /^\(/.test(label ?? '');
    return `<div class="t${win ? ' win' : ''}${tbd ? ' tbd' : ''}"${teamAttr && !tbd ? ` data-team="${n}"` : ''}>${logoImg(g[`t_${s}_logo`])}<span class="name">${esc(tbd ? slotLabel(label) : label)}</span></div>`;
  };
  const live = g.g_status === 'ON';
  return `<div class="duel${when ? '' : ' no-when'}">
    ${when ? `<span class="when">${cap(fmt(g.g_date, { weekday: 'short' }))} ${g.g_time}, terrain ${esc(g.g_pitch)}</span>` : ''}
    <div class="teams">${side('a', has && a > b)}${side('b', has && b > a)}</div>
    <a class="scores${has ? '' : ' pending'}${live ? ' live' : ''}" href="#" aria-label="${has ? `Score ${a} à ${b}` : 'Pas encore joué'}, feuille de match n° ${g.g_number}">${has
      ? `<span class="${a < b ? 'lose' : ''}">${a}</span><span class="${b < a ? 'lose' : ''}">${b}</span>`
      : '<span>–</span><span>–</span>'}</a>
  </div>`;
}

/** Club and committee names are stored in capitals: « CANOE CLUB D'AVRANCHES » reads « Canoe Club d'Avranches ». */
const titleCase = s => (s ?? '').toLowerCase()
  .replace(/(^|[\s'’(-])(\p{L})/gu, (m, a, b) => a + b.toUpperCase())
  .replace(/ (D|L)(['’])/g, (m, l, q) => ` ${l.toLowerCase()}${q}`)
  .replace(/ (De|Du|Des|La|Le|Les|Et|Sur|En)\b/g, m => m.toLowerCase())
  .replace(/\b(Ck|Cd|Cr|Ffck|Asl|Us|As)\b/g, m => m.toUpperCase());
const deptName = s => titleCase((s ?? '').replace(/^COMITE DEPARTEMENTAL (CK )?(DE LA |DE L'|DU |DES |DE )?/i, ''));
const regionName = s => titleCase((s ?? '').replace(/^COMITE REGIONAL /i, '').replace(/ CK$/i, ''));
