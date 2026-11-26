FROM python:3.11-slim
RUN pip install --disable-pip-version-check --no-cache-dir --only-binary=:all: miniaudio==1.61 lameenc==1.8.1
WORKDIR /worker
COPY *.py ./
CMD ["python", "-u", "worker.py"]
