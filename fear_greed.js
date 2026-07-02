document.addEventListener('DOMContentLoaded', () => {
  let cachedNews = [];

  const newsFilter = document.getElementById('news-filter');
  if (newsFilter) {
    newsFilter.addEventListener('change', renderNewsFeed);
  }

  function renderNewsFeed() {
    const feed = document.getElementById('news-feed');
    if (!feed) return;

    const filterVal = newsFilter ? newsFilter.value : 'all';
    let filtered = [...cachedNews];

    if (filterVal === 'gold') {
      filtered = filtered.filter(item => String(item.source).toLowerCase().includes('gold'));
    } else if (filterVal === 'silver') {
      filtered = filtered.filter(item => String(item.source).toLowerCase().includes('silver'));
    } else if (filterVal === 'macro') {
      filtered = filtered.filter(item => String(item.source).toLowerCase().includes('marketwatch'));
    }

    // Trim to top 10 after filtering
    filtered = filtered.slice(0, 10);

    if (filtered.length === 0) {
      feed.innerHTML = '<p class="dash-panel-status is-stale">No recent headlines available for this filter.</p>';
      return;
    }

    feed.innerHTML = filtered.map(item => {
      const title = String(item.title || 'Untitled headline');
      const source = String(item.source || 'News');
      const publishedOn = Number(item.published_on || item.published_at || Math.floor(Date.now() / 1000));
      const url = String(item.url || '#');
      const sentimentTag = item.tag ? String(item.tag).toLowerCase() : 'neutral';
      const badgeClass = sentimentTag === 'bullish' ? 'sent-pos' : (sentimentTag === 'bearish' ? 'sent-neg' : 'sent-neu');

      return `
        <a href="${url}" target="_blank" rel="noreferrer" class="news-item">
          <div>${title}</div>
          <div class="news-meta">
            <span class="sentiment-badge ${badgeClass}">${sentimentTag}</span>
            <span>${source} • ${new Date(publishedOn * 1000).toLocaleString([], { month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' })}</span>
          </div>
        </a>
      `;
    }).join('');
  }

  async function fetchNewsFeed() {
    const feed = document.getElementById('news-feed');
    if (!feed) return;

    try {
      const response = await fetch('api/get_news.php');
      if (!response.ok) {
        feed.innerHTML = '<p class="dash-panel-status is-stale">Unable to load news right now.</p>';
        return;
      }

      const data = await response.json();
      cachedNews = Array.isArray(data?.Data) ? data.Data.slice(0, 20) : [];
      
      // Ensure headlines are sorted newest-first by published timestamp
      const getTs = (it) => Number(it?.published_on ?? it?.published_at ?? 0);
      cachedNews.sort((a, b) => getTs(b) - getTs(a));

      renderNewsFeed();
    } catch (error) {
      feed.innerHTML = '<p class="dash-panel-status is-error">News feed unavailable.</p>';
    }
  }

  async function fetchFearGreedIndex() {
    const response = await fetch('api/get_fear_greed_index.php');
    if (!response.ok) {
      const status = document.getElementById('fear-greed-status');
      if (status) status.textContent = 'Unable to load the sentiment index right now.';
      return;
    }

    const data = await response.json();
    if (data.error) {
      const status = document.getElementById('fear-greed-status');
      if (status) status.textContent = data.error;
      return;
    }

    const score = Math.max(0, Math.min(100, Math.round(Number(data.score ?? 50))));
    const labelMap = [
      { min: 80, text: 'EXTREME GREED', badge: 'badge-extreme-greed', scaleIndex: 4, tone: 'ok' },
      { min: 60, text: 'GREED', badge: 'badge-greed', scaleIndex: 3, tone: 'ok' },
      { min: 40, text: 'NEUTRAL', badge: 'badge-neutral', scaleIndex: 2, tone: 'ok' },
      { min: 20, text: 'FEAR', badge: 'badge-fear', scaleIndex: 1, tone: 'stale' },
      { min: 0, text: 'EXTREME FEAR', badge: 'badge-extreme-fear', scaleIndex: 0, tone: 'error' }
    ];
    const label = labelMap.find(item => score >= item.min) || labelMap[labelMap.length - 1];

    const fill = document.getElementById('fear-greed-fill');
    const value = document.getElementById('fear-greed-value');
    const badge = document.getElementById('fear-greed-label');
    const status = document.getElementById('fear-greed-status');
    const updated = document.getElementById('fear-greed-last-updated');
    const components = document.getElementById('fear-greed-components');

    if (fill) fill.style.height = `${score}%`;
    if (value) value.textContent = score;
    if (badge) {
      badge.className = `g-badge ${label.badge}`;
      badge.textContent = label.text;
    }

    // Dynamic scale active check and highlighting
    const scaleSpans = document.querySelectorAll('.sentiment-scale span');
    scaleSpans.forEach((span, idx) => {
      span.classList.remove('active', 'extreme-fear', 'fear', 'neutral', 'greed', 'extreme-greed');
      if (idx === label.scaleIndex) {
        span.classList.add('active');
        if (label.scaleIndex === 0) span.classList.add('extreme-fear');
        else if (label.scaleIndex === 1) span.classList.add('fear');
        else if (label.scaleIndex === 2) span.classList.add('neutral');
        else if (label.scaleIndex === 3) span.classList.add('greed');
        else if (label.scaleIndex === 4) span.classList.add('extreme-greed');
      }
    });
    if (status) status.textContent = (data.description !== undefined && data.description !== null) ? data.description : 'Composite market pressure is balanced.';
    if (updated && data.calculated_at) {
      const parsed = new Date(String(data.calculated_at).replace(' ', 'T'));
      if (!Number.isNaN(parsed.getTime())) {
        updated.textContent = `Last updated: ${parsed.toLocaleString([], { month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' })}`;
      }
    }

    if (components && Array.isArray(data.components)) {
      components.innerHTML = data.components.map(component => {
        const valueNumber = Math.max(0, Math.min(100, Number(component.value ?? 50)));
        return `
          <div class="fear-greed-component">
            <span class="component-label">${component.label}</span>
            <span class="component-value">${valueNumber}</span>
            <div class="fear-greed-bar"><span style="width:${valueNumber}%"></span></div>
            <span class="component-detail">${component.detail || ''}</span>
          </div>
        `;
      }).join('');
    }
  }

  fetchFearGreedIndex();
  fetchNewsFeed();
  // Refresh every 15 minutes to match the server-side cache TTL
  setInterval(fetchFearGreedIndex, 15 * 60 * 1000);
  // Refresh news every 30 minutes
  setInterval(fetchNewsFeed, 30 * 60 * 1000);

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      fetchFearGreedIndex();
      fetchNewsFeed();
    }
  });
});