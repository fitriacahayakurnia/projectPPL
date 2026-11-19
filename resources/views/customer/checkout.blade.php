@extends('layouts.app')

@section('title', 'Checkout – Elektronik Modern')

@section('head')
@endsection

@section('header')
    @include('partials.header')
@endsection

@section('content')
    <section class="py-8 md:py-[72px] bg-g50 min-h-screen px-4 md:px-8">
        <div class="max-w-[1280px] mx-auto" id="checkoutPageContainer">
            <div class="flex items-center gap-1.5 mb-6 text-[13px]">
                <a href="{{ route('index') }}" class="text-g500 hover:text-primary transition-colors flex items-center gap-1.5"><i class="fi fi-rr-home"></i> Beranda</a> 
                <i class="fi fi-rr-angle-small-right text-g400"></i> 
                <a href="{{ route('cart.index') }}" class="text-g500 hover:text-primary transition-colors">Keranjang</a>
                <i class="fi fi-rr-angle-small-right text-g400"></i>
                <span class="text-g800 font-semibold">Checkout</span>
            </div>
            
            <div class="mb-8">
                <h1 class="font-heading text-[28px] md:text-[32px] font-extrabold text-g900 mb-2 flex items-center gap-3">
                    <i class="fi fi-rr-shopping-bag text-primary"></i> Checkout
                </h1>
                <p class="text-g500 text-[15px]">Periksa pesanan Anda sebelum melanjutkan pembayaran</p>
            </div>

            <div class="flex flex-col lg:flex-row gap-8 items-start">
                <!-- Main Form Section -->
                <div class="flex-1 w-full space-y-5">
                    <form id="checkoutForm" method="POST" action="{{ route('customer.placeOrder') }}" enctype="multipart/form-data">
                        @csrf
                        <!-- Items -->
                        <x-card class="p-6 mb-5">
                            <h3 class="font-heading text-lg font-extrabold text-g900 mb-5 flex items-center gap-2">
                                <i class="fi fi-rr-box text-primary"></i> Produk Dipesan
                            </h3>
                            <div class="flex flex-col divide-y divide-g100">
                            @foreach ($items as $item)
                                <div class="py-3.5 flex items-center gap-4 group">
                                    <div class="w-16 h-16 rounded-xl bg-g50 border border-g100 p-1 flex items-center justify-center shrink-0">
                                        @if($item->produk->gambar)
                                            <img src="{{ asset('storage/products/' . $item->produk->gambar) }}" alt="{{ $item->produk->nama_produk }}" loading="lazy" decoding="async" class="w-full h-full object-contain mix-blend-multiply">
                                        @else
                                            <i class="fi fi-rr-picture text-2xl text-g300"></i>
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-[13px] font-bold text-g800 mb-1 leading-snug">{{ $item->produk->nama_produk }}</div>
                                        <div class="text-xs text-g500">{{ $item->qty }} × Rp {{ number_format($item->produk->harga, 0, ',', '.') }}</div>
                                    </div>
                                    <div class="font-heading font-extrabold text-primary text-[15px] whitespace-nowrap pl-4">
                                        Rp {{ number_format($item->lineTotal, 0, ',', '.') }}
                                    </div>
                                </div>
                            @endforeach
                            </div>
                        </x-card>

                        <!-- Alamat Pengiriman -->
                        <x-card class="p-6 mb-5">
                            <h3 class="font-heading text-lg font-extrabold text-g900 mb-5 flex items-center gap-2">
                                <i class="fi fi-rr-map-marker text-primary"></i> Alamat Pengiriman
                            </h3>
                            @if ($alamats->count())
                                <div class="space-y-3">
                                @foreach ($alamats as $alamat)
                                    <label class="flex items-start gap-3 p-4 bg-white rounded-xl cursor-pointer border-[1.5px] border-g200 hover:border-primary transition-all has-[:checked]:border-primary has-[:checked]:bg-primary-light/50 has-[:checked]:ring-4 has-[:checked]:ring-primary/10">
                                        <input type="radio" name="id_alamat" value="{{ $alamat->id_alamat }}"
                                            {{ $loop->first ? 'checked' : '' }}
                                            class="w-4 h-4 mt-0.5 accent-primary shrink-0 rounded-full text-primary focus:ring-primary">
                                        <div>
                                            <div class="text-[14px] font-bold text-g800 mb-0.5">{{ $alamat->label_alamat }}</div>
                                            <div class="text-[13px] text-g600 leading-relaxed mb-1">{{ $alamat->detail_alamat }}</div>
                                            <div class="text-[12px] font-semibold text-g500 flex items-center gap-1.5"><i class="fi fi-rr-phone-call"></i> {{ $alamat->nomor_telepon }}</div>
                                        </div>
                                    </label>
                                @endforeach
                                </div>
                            @else
                                <x-alert type="warning" class="mb-4">
                                    Anda belum memiliki alamat pengiriman. Tambahkan alamat terlebih dahulu melalui halaman profil.
                                </x-alert>
                                <x-button type="button" variant="outline" onclick="window.location='{{ route('customer.profile') }}?tab=alamat'">
                                    <i class="fi fi-rr-plus"></i> Tambah Alamat
                                </x-button>
                            @endif
                        </x-card>

                        <!-- Ekspedisi -->
                        <x-card class="p-6">
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                                <h3 class="font-heading text-lg font-extrabold text-g900 flex items-center gap-2">
                                    <i class="fi fi-rr-truck-side text-primary"></i> Ekspedisi Pengiriman
                                </h3>
                                <span id="shippingRateSourceBadge" class="inline-flex items-center gap-1.5 bg-blue-50 text-blue-600 border border-blue-200 py-1 px-3 rounded-full text-[11px] font-bold">
                                    <i class="fi fi-rr-marker"></i> Dikirim dari {{ config('services.rajaongkir.origin_search', 'Surabaya') }}
                                </span>
                            </div>

                            <div class="mb-4 flex flex-wrap items-center justify-between gap-2 bg-g50 border border-g200 rounded-xl px-4 py-2.5 text-[12px] font-semibold text-g600">
                                <span>
                                    Total Berat Paket:
                                    <strong class="text-g900">{{ number_format(($totalWeight ?? 1000) / 1000, 2, ',', '.') }} kg</strong>
                                    ({{ number_format($totalWeight ?? 1000) }} gr)
                                </span>
                                <span id="shippingDestinationLabel" class="text-g500"></span>
                            </div>

                            <input type="hidden" name="layanan_pengiriman" id="inputLayananPengiriman" value="">
                            <input type="hidden" name="estimasi_hari" id="inputEstimasiHari" value="">

                            <div id="shippingLoading" class="hidden py-6 text-center text-[13px] font-semibold text-g500">
                                Menghitung estimasi ongkos kirim berdasarkan alamat dan berat...
                            </div>

                            <div id="shippingOptionsContainer" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                @foreach($ekspedisis as $index => $ekspedisi)
                                    <label class="ship-opt flex items-center gap-3.5 p-4 bg-white rounded-xl cursor-pointer border-[1.5px] transition-all {{ $index === 0 ? 'border-primary bg-primary-light shadow-[0_0_0_4px_rgba(26,92,255,0.1)]' : 'border-g200 hover:border-g300' }}" onclick="selectShip(this)">
                                        <input type="radio" name="id_ekspedisi" value="{{ $ekspedisi->id_ekspedisi }}" data-biaya="{{ $ekspedisi->biaya_pengiriman }}" data-service="REG" data-etd="2-4 hari" {{ $index === 0 ? 'checked' : '' }} class="w-4 h-4 accent-primary shrink-0" />
                                        <div>
                                            <div class="font-bold text-[14px] text-g800 mb-0.5">{{ $ekspedisi->nama_ekspedisi }} - REG</div>
                                            <div class="text-[11px] font-medium text-g500 mb-0.5">Estimasi 2-4 hari</div>
                                            <div class="text-[13px] font-bold text-primary">Rp {{ number_format($ekspedisi->biaya_pengiriman, 0, ',', '.') }}</div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </x-card>

                        <!-- Pembayaran & Upload -->
                        <!-- Pembayaran & Upload -->
                        <x-card class="p-6">
                            <h3 class="font-heading text-lg font-extrabold text-g900 mb-5 flex items-center gap-2">
                                <i class="fi fi-rr-credit-card text-primary"></i> Metode Pembayaran
                            </h3>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <label class="pay-opt flex items-center gap-3.5 p-4 bg-white rounded-xl cursor-pointer border-[1.5px] transition-all border-primary bg-primary-light shadow-[0_0_0_4px_rgba(26,92,255,0.1)]" onclick="selectPay(this, 'midtrans')">
                                    <input type="radio" name="metode_pembayaran" value="Midtrans Sandbox" checked class="w-4 h-4 accent-primary shrink-0" />
                                    <div class="text-[24px] text-primary flex items-center justify-center bg-white w-11 h-11 rounded-full shadow-sm shrink-0"><i class="fi fi-rr-credit-card"></i></div>
                                    <div>
                                        <div class="font-bold text-[14px] text-g800 mb-0.5">Midtrans</div>
                                        <div class="text-[10px] font-medium text-g500 uppercase tracking-wider">VA · QRIS · E-Wallet</div>
                                    </div>
                                </label>
                                <label class="pay-opt flex items-center gap-3.5 p-4 bg-white rounded-xl cursor-pointer border-[1.5px] border-g200 transition-all hover:border-g300" onclick="selectPay(this, 'bank')">
                                    <input type="radio" name="metode_pembayaran" value="Transfer Bank" class="w-4 h-4 accent-primary shrink-0" />
                                    <div class="text-[24px] text-primary flex items-center justify-center bg-white w-11 h-11 rounded-full shadow-sm shrink-0"><i class="fi fi-rr-bank"></i></div>
                                    <div>
                                        <div class="font-bold text-[14px] text-g800 mb-0.5">Transfer Bank</div>
                                        <div class="text-[10px] font-medium text-g500 uppercase tracking-wider">Manual Upload</div>
                                    </div>
                                </label>
                                <label class="pay-opt flex items-center gap-3.5 p-4 bg-white rounded-xl cursor-pointer border-[1.5px] border-g200 transition-all hover:border-g300" onclick="selectPay(this, 'ewallet')">
                                    <input type="radio" name="metode_pembayaran" value="E-Wallet" class="w-4 h-4 accent-primary shrink-0" />
                                    <div class="text-[24px] text-green-500 flex items-center justify-center bg-green-50 w-11 h-11 rounded-full border border-green-100 shrink-0"><i class="fi fi-rr-wallet"></i></div>
                                    <div>
                                        <div class="font-bold text-[14px] text-g800 mb-0.5">E-Wallet</div>
                                        <div class="text-[10px] font-medium text-g500 uppercase tracking-wider">Manual Upload</div>
                                    </div>
                                </label>
                            </div>

                            <div id="midtransDetail" class="mt-4 bg-primary-light/50 border border-primary/20 rounded-xl p-5 border-l-4 border-l-primary">
                                <div class="font-bold text-primary mb-1 flex items-center gap-2"><i class="fi fi-rr-shield-check"></i> Pembayaran Otomatis via Midtrans Sandbox</div>
                                <div class="text-[13px] text-g600">Anda akan diarahkan ke halaman pembayaran Midtrans (Virtual Account, QRIS, GoPay, dll.) setelah menekan tombol <strong>Buat Pesanan</strong>.</div>
                            </div>
                            
                            <div id="bankDetail" class="mt-4 bg-primary-light/50 border border-primary/20 rounded-xl p-5 border-l-4 border-l-primary hidden">
                                <div class="font-bold text-primary mb-2 flex items-center gap-2"><i class="fi fi-rr-document"></i> Detail Transfer Bank:</div>
                                <div class="text-[14px] text-g800 mb-1"><strong>Bank BCA</strong> – No. Rek: <strong class="text-primary font-heading text-lg ml-1 tracking-wider">1234-5678-90</strong></div>
                                <div class="text-[13px] font-medium text-g500">a.n. Elektronik Modern Store</div>
                            </div>

                            <div id="ewalletDetail" class="mt-4 bg-green-50 border border-green-200 rounded-xl p-5 border-l-4 border-l-green-500 hidden">
                                <div class="font-bold text-green-700 mb-2 flex items-center gap-2"><i class="fi fi-rr-smartphone"></i> Detail E-Wallet:</div>
                                <div class="text-[14px] text-g800 mb-1"><strong>DANA / ShopeePay</strong> – No. HP: <strong class="text-green-600 font-heading text-lg ml-1 tracking-wider">0812-3456-7890</strong></div>
                                <div class="text-[13px] font-medium text-g500">a.n. Elektronik Modern Admin</div>
                            </div>

                            <div id="uploadSection" class="mt-8 hidden">
                                <x-label class="mb-3">Upload Bukti Pembayaran</x-label>
                                <div class="border-2 border-dashed border-g300 rounded-2xl p-8 text-center text-g500 cursor-pointer transition-all hover:border-primary hover:bg-primary-light group" onclick="document.getElementById('bukti_bayar').click()">
                                    <div class="text-[40px] mb-3 text-g300 group-hover:text-primary transition-colors inline-flex"><i class="fi fi-rr-cloud-upload"></i></div>
                                    <div class="font-bold text-[15px] mb-1.5 text-g700 group-hover:text-primary transition-colors">Klik atau seret file ke sini</div>
                                    <div class="text-[12px] font-medium">Format: JPG, PNG, PDF · Maks. 5MB</div>
                                    <input type="file" id="bukti_bayar" name="bukti_bayar" accept="image/*,.pdf" class="hidden" onchange="handleUpload(this); validateCheckoutReady()" />
                                </div>
                                <div id="uploadPreview" class="hidden mt-4 bg-green-50 border border-green-200 rounded-xl p-3 text-[13px] text-green-700 font-bold flex items-center gap-2">
                                    <i class="fi fi-rr-check-circle text-lg"></i> <span id="uploadPreviewMsg"></span>
                                </div>
                            </div>
                        </x-card>
                    </form>
                </div>

                <!-- Summary Section (Outside checkout form to enable smooth voucher logic) -->
                <div class="w-full lg:w-[380px] shrink-0 lg:sticky lg:top-[84px]">
                    <div class="bg-white rounded-[2.5rem] shadow-card p-6 md:p-8 border border-g100">
                        <h3 class="font-heading text-xl font-extrabold text-g900 mb-6">Ringkasan Belanja</h3>

                        <div class="space-y-4">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-g500 font-medium">Subtotal ({{ count($items) }} produk)</span>
                                <span class="font-bold text-g900">Rp {{ number_format($subtotal, 0, ',', '.') }}</span>
                            </div>

                            @if ($appliedPromo)
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-green-600 font-bold flex items-center gap-2">
                                        <i class="fi fi-rr-ticket"></i> Voucher ({{ $appliedPromo->kode_voucher }})
                                    </span>
                                    <span class="font-bold text-green-600">- Rp {{ number_format($discount, 0, ',', '.') }}</span>
                                </div>
                            @endif

                            <div class="flex justify-between items-center text-sm">
                                <span class="text-g500 font-medium">Ongkos Kirim</span>
                                <span class="font-bold text-g800" id="shippingCost">Rp 0</span>
                            </div>
                        </div>

                        <!-- Voucher Form -->
                        <x-voucher-panel 
                            :appliedPromo="$appliedPromo" 
                            :promos="$promos" 
                            inputId="checkout_voucher_input" 
                            formId="voucherFormCheckout" 
                        />

                        <div class="flex justify-between items-center mt-8 pt-6 border-t-2 border-dashed border-g100">
                            <span class="text-base font-bold text-g900">Total Pembayaran</span>
                            <span class="font-heading text-2xl font-extrabold text-primary" id="totalPayment">Rp {{ number_format(max(0, $subtotal - $discount), 0, ',', '.') }}</span>
                        </div>

                        <!-- Place Order Button linked to #checkoutForm -->
                        <button type="submit" form="checkoutForm" id="btnSubmitCheckout" class="w-full mt-8 py-4 px-6 bg-primary text-white rounded-2xl font-bold text-[15px] shadow-xl shadow-primary/20 opacity-60 cursor-not-allowed pointer-events-none transition-all flex items-center justify-center gap-2">
                            Buat Pesanan <i class="fi fi-rr-arrow-right"></i>
                        </button>

                        <div class="mt-6 flex items-center justify-center gap-2 text-[10px] font-bold text-g400 uppercase tracking-widest">
                            <i class="fi fi-rr-lock text-xs"></i> Transaksi aman & terenkripsi
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @push('scripts')
    <script>
        const baseSubtotal = {{ max(0, $subtotal - ($discount ?? 0)) }};
        const shippingRatesUrl = "{{ route('customer.checkout.shippingRates') }}";
        const csrfToken = "{{ csrf_token() }}";

        function formatRupiah(num) {
            return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
        }

        function escapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function updateOrderSummary() {
            const selectedEkspedisi = document.querySelector('input[name="id_ekspedisi"]:checked');
            const ongkir = selectedEkspedisi ? (parseInt(selectedEkspedisi.getAttribute('data-biaya')) || 0) : 0;
            const total = baseSubtotal + ongkir;

            document.getElementById('shippingCost').textContent = formatRupiah(ongkir);
            const totalPaymentEl = document.getElementById('totalPayment');
            if (totalPaymentEl) {
                totalPaymentEl.textContent = formatRupiah(total);
            }

            const serviceInput = document.getElementById('inputLayananPengiriman');
            const etdInput = document.getElementById('inputEstimasiHari');
            if (serviceInput && selectedEkspedisi) {
                serviceInput.value = selectedEkspedisi.getAttribute('data-service') || 'REG';
            }
            if (etdInput && selectedEkspedisi) {
                etdInput.value = selectedEkspedisi.getAttribute('data-etd') || '2-4 hari';
            }
        }

        function validateCheckoutReady() {
            const btn = document.getElementById('btnSubmitCheckout');
            const fileInput = document.getElementById('bukti_bayar');
            const selectedMethod = document.querySelector('input[name="metode_pembayaran"]:checked')?.value;
            const isMidtrans = selectedMethod === 'Midtrans Sandbox';
            const hasAlamat = {{ $alamats->isNotEmpty() ? 'true' : 'false' }};
            const hasEkspedisi = !!document.querySelector('input[name="id_ekspedisi"]:checked');

            if (hasAlamat && hasEkspedisi && (isMidtrans || (fileInput && fileInput.files.length > 0))) {
                btn.classList.remove('opacity-60', 'cursor-not-allowed', 'pointer-events-none');
            } else {
                btn.classList.add('opacity-60', 'cursor-not-allowed', 'pointer-events-none');
            }
        }

        function selectAddr(el) {
            document.querySelectorAll('.addr-card').forEach(card => {
                card.classList.remove('border-primary', 'bg-primary-light', 'shadow-[0_0_0_4px_rgba(26,92,255,0.1)]');
                card.classList.add('border-g200');
            });
            el.classList.add('border-primary', 'bg-primary-light', 'shadow-[0_0_0_4px_rgba(26,92,255,0.1)]');
            el.classList.remove('border-g200');

            const radio = el.querySelector('input[name="id_alamat"]');
            if (radio) {
                radio.checked = true;
                fetchShippingRates(radio.value);
            }
        }

        function selectShip(el) {
            document.querySelectorAll('.ship-opt').forEach(opt => {
                opt.classList.remove('border-primary', 'bg-primary-light', 'shadow-[0_0_0_4px_rgba(26,92,255,0.1)]');
                opt.classList.add('border-g200');
            });
            el.classList.add('border-primary', 'bg-primary-light', 'shadow-[0_0_0_4px_rgba(26,92,255,0.1)]');
            el.classList.remove('border-g200');

            const radio = el.querySelector('input[name="id_ekspedisi"]');
            if (radio) {
                radio.checked = true;
            }
            updateOrderSummary();
            validateCheckoutReady();
        }

        function selectPay(el, type) {
            document.querySelectorAll('.pay-opt').forEach(opt => {
                opt.classList.remove('border-primary', 'bg-primary-light', 'shadow-[0_0_0_4px_rgba(26,92,255,0.1)]');
                opt.classList.add('border-g200');
            });
            el.classList.add('border-primary', 'bg-primary-light', 'shadow-[0_0_0_4px_rgba(26,92,255,0.1)]');
            el.classList.remove('border-g200');

            const fileInput = document.getElementById('bukti_bayar');
            document.getElementById('midtransDetail').classList.toggle('hidden', type !== 'midtrans');
            document.getElementById('bankDetail').classList.toggle('hidden', type !== 'bank');
            document.getElementById('ewalletDetail').classList.toggle('hidden', type !== 'ewallet');
            document.getElementById('uploadSection').classList.toggle('hidden', type === 'midtrans');

            if (fileInput) {
                fileInput.required = (type !== 'midtrans');
            }
            validateCheckoutReady();
        }

        function renderShippingRates(rates) {
            const container = document.getElementById('shippingOptionsContainer');
            if (!container || !Array.isArray(rates) || rates.length === 0) return;

            container.innerHTML = rates.map((rate, idx) => {
                const isFirst = idx === 0;
                const activeClasses = isFirst
                    ? 'border-primary bg-primary-light shadow-[0_0_0_4px_rgba(26,92,255,0.1)]'
                    : 'border-g200 hover:border-g300';

                return `
                    <label class="ship-opt flex items-center gap-3.5 p-4 bg-white rounded-xl cursor-pointer border-[1.5px] transition-all ${activeClasses}" onclick="selectShip(this)">
                        <input type="radio"
                               name="id_ekspedisi"
                               value="${escapeHtml(rate.id_ekspedisi)}"
                               data-biaya="${escapeHtml(rate.cost)}"
                               data-service="${escapeHtml(rate.service)}"
                               data-etd="${escapeHtml(rate.etd)}"
                               ${isFirst ? 'checked' : ''}
                               class="w-4 h-4 accent-primary shrink-0" />
                        <div>
                            <div class="font-bold text-[14px] text-g800 mb-0.5">${escapeHtml(rate.courier_name)} - ${escapeHtml(rate.service)}</div>
                            <div class="text-[11px] font-medium text-g500 mb-0.5">Estimasi ${escapeHtml(rate.etd)}</div>
                            <div class="text-[13px] font-bold text-primary">${formatRupiah(rate.cost)}</div>
                        </div>
                    </label>
                `;
            }).join('');

            updateOrderSummary();
            validateCheckoutReady();
        }

        async function fetchShippingRates(idAlamat) {
            if (!idAlamat) return;

            const loadingEl = document.getElementById('shippingLoading');
            const badgeEl = document.getElementById('shippingRateSourceBadge');
            const destLabelEl = document.getElementById('shippingDestinationLabel');

            if (loadingEl) loadingEl.classList.remove('hidden');

            try {
                const response = await fetch(shippingRatesUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ id_alamat: idAlamat })
                });

                if (!response.ok) return;

                const data = await response.json();
                if (badgeEl) {
                    if (data.source === 'api') {
                        badgeEl.className = 'inline-flex items-center gap-1.5 bg-green-50 text-green-700 border border-green-200 py-1 px-3 rounded-full text-[11px] font-bold';
                        badgeEl.innerHTML = `<i class="fi fi-rr-check-circle"></i> RajaOngkir API · Dari ${escapeHtml(data.origin)}`;
                    } else {
                        badgeEl.className = 'inline-flex items-center gap-1.5 bg-orange-50 text-orange-700 border border-orange-200 py-1 px-3 rounded-full text-[11px] font-bold';
                        badgeEl.innerHTML = `<i class="fi fi-rr-info"></i> Tarif Standar · Dari ${escapeHtml(data.origin)}`;
                    }
                }
                if (destLabelEl && data.destination) {
                    destLabelEl.textContent = 'Tujuan: ' + data.destination;
                }

                renderShippingRates(data.rates || []);
            } catch (err) {
                console.error('Gagal mengambil tarif pengiriman:', err);
            } finally {
                if (loadingEl) loadingEl.classList.add('hidden');
            }
        }

        function handleUpload(input) {
            const preview = document.getElementById('uploadPreview');
            const msg = document.getElementById('uploadPreviewMsg');
            if (input.files && input.files[0]) {
                msg.textContent = 'File dipilih: ' + input.files[0].name;
                preview.classList.remove('hidden');
            } else {
                preview.classList.add('hidden');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            updateOrderSummary();
            validateCheckoutReady();

            const initialAlamat = document.querySelector('input[name="id_alamat"]:checked');
            if (initialAlamat) {
                fetchShippingRates(initialAlamat.value);
            }
        });
    </script>
@endpush
@endsection
