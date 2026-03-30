document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('#gpr-reset-form');
  const progressPanel = document.querySelector('#gpr-progress-panel');
  const progressStatus = progressPanel?.querySelector('.gpr-progress__status');
  const progressBar = progressPanel?.querySelector('.gpr-progress__bar');
  const progressMeta = progressPanel?.querySelector('.gpr-progress__meta');
  const resultsPanel = document.querySelector('#gpr-results-panel');
  const resultsBody = document.querySelector('#gpr-results-body');
  const resultsSummary = document.querySelector('#gpr-results-summary');

  if (!form || !window.fetch || !window.gprAdmin) {
    return;
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const role = form.querySelector('[name="gpr_user_role"]').value;
    const excludedUsernames = form.querySelector('[name="gpr_excluded_usernames"]').value;
    const confirmation = form.querySelector('[name="gpr_confirm_reset"]');

    if (!confirmation.checked) {
      confirmation.reportValidity();
      return;
    }

    toggleBusyState(true);
    setProgress(0, 'Preparing password reset job…', '');
    resultsBody.innerHTML = '';
    resultsSummary.innerHTML = '';
    resultsPanel.hidden = false;

    try {
      const startResponse = await postAction('gpr_start_job', {
        role,
        excluded_usernames: excludedUsernames,
      });

      renderResults(startResponse.results || []);
      renderSummary(startResponse.summary, startResponse.scopeLabel, startResponse.excludedUsernames);

      if (!startResponse.hasQueuedUsers) {
        setProgress(100, window.gprAdmin.messages.complete, 'No queued users remained after exclusions.');
        toggleBusyState(false);
        return;
      }

      await processJob();
    } catch (error) {
      setProgress(0, window.gprAdmin.messages.startError, error.message);
      toggleBusyState(false);
    }
  });

  async function processJob() {
    try {
      const response = await postAction('gpr_process_job', {});

      renderResults(response.results || []);
      renderSummary(response.summary, response.scopeLabel, response.excludedUsernames);
      setProgressFromSummary(response.summary);

      if (response.completed) {
        setProgress(100, window.gprAdmin.messages.complete, buildProgressMeta(response.summary));
        toggleBusyState(false);
        return;
      }

      window.setTimeout(processJob, 250);
    } catch (error) {
      setProgress(progressBar.value || 0, window.gprAdmin.messages.processError, error.message);
      toggleBusyState(false);
    }
  }

  async function postAction(action, payload) {
    const body = new URLSearchParams({
      action,
      nonce: window.gprAdmin.nonce,
      ...payload,
    });

    const response = await fetch(window.gprAdmin.ajaxUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      },
      body: body.toString(),
      credentials: 'same-origin',
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      throw new Error(data?.data || 'Request failed.');
    }

    return data.data;
  }

  function renderResults(results) {
    results.forEach((result) => {
      const row = document.createElement('tr');
      const statusClass = `gpr-status gpr-status--${result.status}`;

      row.innerHTML = `
        <td>${escapeHtml(result.username)}</td>
        <td>${escapeHtml(result.email)}</td>
        <td>${escapeHtml(result.role)}</td>
        <td><span class="${statusClass}">${escapeHtml(capitalize(result.status))}</span></td>
        <td>${escapeHtml(result.message)}</td>
      `;

      resultsBody.appendChild(row);
    });
  }

  function renderSummary(summary, scopeLabel, excludedUsernames) {
    resultsSummary.innerHTML = `
      <div class="gpr-summary-grid">
        <div><strong>Scope:</strong> ${escapeHtml(scopeLabel || 'All users')}</div>
        <div><strong>Matched users:</strong> ${summary.total}</div>
        <div><strong>Processed:</strong> ${summary.processed}</div>
        <div><strong>Success:</strong> ${summary.success}</div>
        <div><strong>Failed:</strong> ${summary.failed}</div>
        <div><strong>Skipped:</strong> ${summary.skipped}</div>
      </div>
      ${excludedUsernames ? `<p><strong>Excluded usernames:</strong> ${escapeHtml(excludedUsernames)}</p>` : ''}
    `;
  }

  function setProgressFromSummary(summary) {
    const processed = Number(summary.processed || 0);
    const total = Number(summary.total || 0);
    const percent = total > 0 ? Math.round((processed / total) * 100) : 100;

    setProgress(percent, 'Processing password resets…', buildProgressMeta(summary));
  }

  function buildProgressMeta(summary) {
    return `${summary.processed}/${summary.total} processed, ${summary.success} success, ${summary.failed} failed, ${summary.skipped} skipped`;
  }

  function setProgress(percent, status, meta) {
    progressPanel.hidden = false;
    progressBar.value = percent;
    progressStatus.textContent = status;
    progressMeta.textContent = meta;
  }

  function toggleBusyState(isBusy) {
    form.querySelectorAll('button, input, select, textarea').forEach((field) => {
      field.disabled = isBusy;
    });
  }

  function capitalize(value) {
    return value.charAt(0).toUpperCase() + value.slice(1);
  }

  function escapeHtml(value) {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }
});
