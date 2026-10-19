      </div>
    </section>
  </div>

  <footer class="main-footer"><strong>Hotel Management</strong> &mdash; AdminLTE demo</footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js"></script>
<script>
  // COMPONENT: Select (Select2 dropdown)
  $('.select2').select2({ theme: 'bootstrap4', width: '100%' });

  // COMPONENT: Spinner / Loading overlay
  function showSpinner() { $('.loading-overlay').removeClass('d-none'); }
  function hideSpinner() { $('.loading-overlay').addClass('d-none'); }

  // Show spinner when a form is submitted or an auto-submit select changes
  $('form').on('submit', showSpinner);
  $('.auto-submit').on('change', function () { showSpinner(); this.form.submit(); });

  // Hide spinner once the page has finished loading (small delay so it is visible)
  $(window).on('load pageshow', function () { setTimeout(hideSpinner, 400); });
</script>
</body>
</html>
