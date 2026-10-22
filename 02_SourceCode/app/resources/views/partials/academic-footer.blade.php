{{-- Chân trang học thuật tĩnh phía máy chủ: có trong mã nguồn HTML ("View source") kể cả khi JavaScript tắt.
     Trang Inertia ẩn bản này bằng CSS khi Vue đã mount (#app[data-v-app]) và app.js gỡ hẳn phần tử #static-footer,
     vì layout Vue đã có chân trang riêng (AppFooter). --}}
<footer id="static-footer" class="ssr-academic-footer" data-academic-footer>
    <p>
        <span>{{ __('footer.course') }}: <strong>{{ config('disanso.academic.course', 'Lập trình ứng dụng web trong du lịch 2') }}</strong></span>
        · <span>{{ __('footer.course_code') }}: <strong>{{ config('disanso.academic.course_code', 'CSE703073') }}</strong></span>
        · <span>{{ __('footer.team') }}: <strong>{{ config('disanso.academic.team', 'DiSanSo') }}</strong></span>
    </p>
    <p>{{ __('messages.app.notice') }}</p>
</footer>
