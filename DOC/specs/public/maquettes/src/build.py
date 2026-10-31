"""Builds the mockups into app3's public folder, with the api2 data captured in the parent folder."""
import pathlib, shutil
here = pathlib.Path(__file__).parent
data = here.parent / 'data'
out = here.parents[4] / 'sources/app3/public/maquettes'  # git-ignored preview, served on beta.kpi.localhost
out.mkdir(exist_ok=True)
SOURCES = {
    '__COMP__': 'competition_2026_NPOH.json', '__GAMES__': 'competition_2026_NPOH_games.json',
    '__RANKING__': 'competition_2026_NPOH_ranking.json', '__INFO__': 'competition_2026_NPOH_info.json',
    '__CHART__': 'data_competition_2026_NPOH_charts.json', '__SCORERS__': 'data_scorers.json',
    '__CFH_COMP__': 'data_cfh25.json', '__CFH_CHART__': 'data_cfh25_charts.json',
    '__EVENT__': 'data_event_242_competitions.json', '__EVENT_GAMES__': 'data_event_242_games.json',
    '__EVENT_RANKINGS__': 'data_event_242_rankings.json', '__EVENTS__': 'data_events_all.json',
    '__FIXTURES__': 'fixtures_site.json', '__HOME__': 'home.json',
    '__GROUPS__': 'data_groups_2026.json', '__N1H__': 'data_n1h.json', '__N1H_RANKING__': 'data_n1h_ranking.json',
    '__SEASONS__': 'data_seasons.json', '__CALENDAR__': 'data_calendar_year.json',
    '__NEWS__': 'data_news.json', '__NEWS_CATS__': 'data_news_cats.json', '__ARTICLE__': 'data_article.json',
    '__HISTORY__': 'data_history_N1H.json', '__HISTORY_GROUPS__': 'data_history.json', '__HISTORY_LOGOS__': 'data_history_logos.json',
    '__CLUBS__': 'data_club_details.json', '__TEAM__': 'data_team_67.json',
    '__SEARCH__': 'data_search.json', '__PAGE__': 'data_page.json', '__MENU__': 'data_menu.json', '__FORM_FIELDS__': 'data_form_fields.json',
}
cache = {}
for tpl in sorted(here.glob('*.tpl.html')):
    t = tpl.read_text()
    for key, file in SOURCES.items():
        if key in t:
            cache.setdefault(file, (data / file).read_text())
            t = t.replace(key, cache[file])
    (out / tpl.name.replace('.tpl', '')).write_text(t)
for f in ['maquette.css', 'maquette.js', 'index.html']:
    if (here / f).exists():
        shutil.copy(here / f, out / f)
print(sorted(p.name for p in out.iterdir()))
