FROM python:3.12-slim
WORKDIR /srv
ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1
COPY requirements.txt .
RUN pip install --no-cache-dir --upgrade pip && pip install --no-cache-dir -r requirements.txt
COPY app ./app
COPY kiem_dinh_du_lieu.py run.py ./
RUN useradd --system --uid 10001 --no-create-home analytics && chown -R analytics:analytics /srv
USER analytics
EXPOSE 8001
ENV UVICORN_HOST=dual PORT=8001
# Kiểm tra sức khỏe bằng Python (không cần cài curl qua apt)
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s \
  CMD python -c "import os,urllib.request;urllib.request.urlopen('http://127.0.0.1:%s/healthz' % os.getenv('PORT','8001'),timeout=4)" || exit 1
# run.py nghe cả IPv4 và IPv6 (Railway: mạng riêng IPv6, healthcheck IPv4); đọc PORT do nền tảng cấp
CMD ["python", "run.py"]
