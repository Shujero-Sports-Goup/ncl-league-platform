// fixture.js - Advanced Scheduling Frontend Engine

let conflictAbort = null;
let conflictTimer = null;
let cache = {};
let cacheExpiry = {};

const apiBase = '/admin/api_fixture.php';

// --- Select2 Config: Team & Venue Search
$('.select-team').select2({
  placeholder: "Select team",
  width: '100%',
  ajax: {
    delay: 300,
    url: `${apiBase}?action=search_teams`,
    dataType: 'json',
    data: params => ({ q: params.term, page: params.page || 1 }),
    processResults: data => ({ results: data.results }),
    cache: true
  }
});

$('.select-venue').select2({
  placeholder: "Select venue",
  width: '100%',
  ajax: {
    delay: 300,
    url: `${apiBase}?action=search_venues`,
    dataType: 'json',
    data: params => ({ q: params.term, page: params.page || 1 }),
    processResults: data => ({ results: data.results }),
    cache: true
  }
});

// --- Info Display Functions
$('#home_team').on('change', async function() {
  let id = $(this).val();
  $('#home_info').text('');
  if (!id) return;
  let info = await cachedFetch('team_stats', { team_id: id });
  if (info.success) {
    let s = info.stats;
    $('#home_info').text(`Total: ${s.total}, Upcoming: ${s.upcoming}, Last: ${s.last_played?.match_date || 'N/A'}`);
  }
  renderPreview();
  debounceConflictCheck();
});

$('#away_team').on('change', async function() {
  let id = $(this).val();
  $('#away_info').text('');
  if (!id) return;
  let info = await cachedFetch('team_stats', { team_id: id });
  if (info.success) {
    let s = info.stats;
    $('#away_info').text(`Total: ${s.total}, Upcoming: ${s.upcoming}, Last: ${s.last_played?.match_date || 'N/A'}`);
  }
  renderPreview();
  debounceConflictCheck();
});

$('#venue').on('change', async function() {
  let id = $(this).val();
  $('#venue_info').text('');
  if (!id) return;
  let info = await cachedFetch('venue_info', { venue_id: id });
  if (info.success) {
    let v = info.info;
    $('#venue_info').text(`Cap: ${v.capacity}, Loc: ${v.location}, Surface: ${v.surface_type}, Booked: ${v.bookings}`);
  }
  renderPreview();
  debounceConflictCheck();
});

$('#match_date,#match_time,#priority,#broadcast').on('input change', () => {
  renderPreview();
  debounceConflictCheck();
});

// --- Debounce Conflict Check
function debounceConflictCheck() {
  if (conflictTimer) clearTimeout(conflictTimer);
  conflictTimer = setTimeout(() => {
    checkConflict();
  }, 500);
}

// --- Conflict Check Engine
function checkConflict() {
  if (conflictAbort) conflictAbort.abort();
  conflictAbort = new AbortController();

  let data = {
    action: 'check_conflict',
    home: $('#home_team').val(),
    away: $('#away_team').val(),
    date: $('#match_date').val(),
    time: $('#match_time').val(),
    venue: $('#venue').val()
  };

  if (!data.home || !data.away || !data.date || !data.time || !data.venue || data.home === data.away) return;

  $('#conflictWarnings').html(`<div class="alert alert-info">Checking conflicts…</div>`);
  $('#submitBtn').prop('disabled', true);

  fetch(`${apiBase}?` + new URLSearchParams(data), { signal: conflictAbort.signal })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        if (res.conflicts.length) {
          $('#conflictWarnings').html(`<div class="alert alert-danger pulse">
            <strong>Conflicts detected:</strong><ul>` +
            res.conflicts.map(c => `<li>${c}</li>`).join('') + `</ul>
            <small><em>${res.suggestions.join(', ')}</em></small></div>`);
        } else {
          $('#conflictWarnings').html(`<div class="alert alert-success pulse">✅ No conflicts detected.</div>`);
          $('#submitBtn').prop('disabled', false);
        }
      }
    }).catch(err => {
      if (err.name !== 'AbortError') {
        $('#conflictWarnings').html(`<div class="alert alert-warning">⚠ Error checking conflicts.</div>`);
      }
    });
}

// --- Fixture Preview Renderer
function renderPreview() {
  const home = $('#home_team').select2('data')[0]?.text;
  const away = $('#away_team').select2('data')[0]?.text;
  const date = $('#match_date').val();
  const time = $('#match_time').val();
  const venue = $('#venue').select2('data')[0]?.text;
  const priority = $('#priority').val();
  const broadcast = $('#broadcast').is(':checked');

  if (!home || !away || !date || !time || !venue) {
    $('#fixturePreview').addClass('d-none');
    return;
  }

  let badge = broadcast ? `<span class="badge bg-warning ms-2">📡 Broadcast</span>` : '';
  $('#fixturePreview').removeClass('d-none').html(`
    <div class="fw-bold">${home} vs ${away}</div>
    <div>${date} @ ${time} <span class="badge bg-primary ms-2">${priority}</span> ${badge}</div>
    <div class="text-muted">${venue}</div>
  `);
}

// --- Caching Utility
async function cachedFetch(action, params) {
  const key = action + ':' + JSON.stringify(params);
  const now = Date.now();
  if (cache[key] && cacheExpiry[key] > now) return cache[key];

  const res = await fetch(`${apiBase}?action=${action}&` + new URLSearchParams(params));
  const data = await res.json();
  cache[key] = data;
  cacheExpiry[key] = now + 300000;
  return data;
}
