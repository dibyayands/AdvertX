
module minsoc_onchip_ram(
`ifdef BIST
	// RAM BIST
	mbist_si_i, mbist_so_o, mbist_ctrl_i,
`endif
	// Generic synchronous single-port RAM interface
	clk, rst, ce, we, oe, addr, di, doq
);

//
// Default address and data buses width
//
parameter aw = 11;
parameter dw = 8;

`ifdef BIST
//
// RAM BIST
//
input mbist_si_i;
input [`MBIST_CTRL_WIDTH - 1:0] mbist_ctrl_i;
output mbist_so_o;
`endif

//
// Generic synchronous single-port RAM interface
//
input			clk;	// Clock
input			rst;	// Reset
input			ce;	// Chip enable input
input			we;	// Write enable input
input			oe;	// Output enable input
input 	[aw-1:0]	addr;	// address bus inputs
input	[dw-1:0]	di;	// input data bus
output	[dw-1:0]	doq;	// output data bus

//
// Decide memory implementation for Xilinx FPGAs
//
`ifdef SPARTAN2
	`define MINSOC_XILINX_RAMB4
`elsif VIRTEX
	`define MINSOC_XILINX_RAMB4
`endif	// !SPARTAN2/VIRTEX

`ifdef SPARTAN3
	`define MINSOC_XILINX_RAMB16
`elsif SPARTAN3E
	`define MINSOC_XILINX_RAMB16
`elsif SPARTAN3A
	`define MINSOC_XILINX_RAMB16
`elsif VIRTEX2
	`define MINSOC_XILINX_RAMB16
`elsif VIRTEX4
	`define MINSOC_XILINX_RAMB16
`elsif VIRTEX5
	`define MINSOC_XILINX_RAMB16
`elsif SPARTAN6
	`define MINSOC_XILINX_RAMB16
`endif	// !SPARTAN3/SPARTAN3E/SPARTAN3A/VIRTEX2/VIRTEX4/VIRTEX5/SPARTAN6


//
// Internal wires and registers
//

`ifdef ARTISAN_SSP
`else
`ifdef VIRTUALSILICON_SSP
`else
`ifdef BIST
assign mbist_so_o = mbist_si_i;
`endif
`endif
`endif


`ifdef GENERIC_MEMORY
//
// Generic single-port synchronous RAM model
//

//
// Generic RAM's registers and wires
//
reg	[dw-1:0]	mem [(1<<aw)-1:0];	// RAM content
reg	[aw-1:0]	addr_reg;		// RAM address register

//
// Data output drivers
//
assign doq = (oe) ? mem[addr_reg] : {dw{1'bZ}};

//
// RAM address register
//
always @(posedge clk or posedge rst)
	if (rst)
		addr_reg <= #1 {aw{1'b0}};
	else if (ce)
		addr_reg <= #1 addr;

//
// RAM write
//
always @(posedge clk)
	if (ce && we)
		mem[addr] <= #1 di;


`elsif ARTISAN_SSP
//
// Instantiation of ASIC memory:
//
// Artisan Synchronous Single-Port RAM (ra1sh)
//
`ifdef UNUSED
art_hssp_2048x8 #(dw, 1<<aw, aw) artisan_ssp(
`else
`ifdef BIST
art_hssp_2048x8_bist artisan_ssp(
`else
art_hssp_2048x8 artisan_ssp(
`endif
`endif
`ifdef BIST
	// RAM BIST
	.mbist_si_i(mbist_si_i),
	.mbist_so_o(mbist_so_o),
	.mbist_ctrl_i(mbist_ctrl_i),
`endif
	.CLK(clk),
	.CEN(~ce),
	.WEN(~we),
	.A(addr),
	.D(di),
	.OEN(~oe),
	.Q(doq)
);


`elsif AVANT_ATP
//
// Instantiation of ASIC memory:
//
// Avant! Asynchronous Two-Port RAM
//
avant_atp avant_atp(
	.web(~we),
	.reb(),
	.oeb(~oe),
	.rcsb(),
	.wcsb(),
	.ra(addr),
	.wa(addr),
	.di(di),
	.doq(doq)
);


`elsif VIRAGE_SSP
//
// Instantiation of ASIC memory:
//
// Virage Synchronous 1-port R/W RAM
//
virage_ssp virage_ssp(
	.clk(clk),
	.adr(addr),
	.d(di),
	.we(we),
	.oe(oe),
	.me(ce),
	.q(doq)
);


`elsif VIRTUALSILICON_SSP
//
// Instantiation of ASIC memory:
//
// Virtual Silicon Single-Port Synchronous SRAM
//
`ifdef UNUSED
vs_hdsp_2048x8 #(1<<aw, aw-1, dw-1) vs_ssp(
`else
`ifdef BIST
vs_hdsp_2048x8_bist vs_ssp(
`else
vs_hdsp_2048x8 vs_ssp(
`endif
`endif
`ifdef BIST
	// RAM BIST
	.mbist_si_i(mbist_si_i),
	.mbist_so_o(mbist_so_o),
	.mbist_ctrl_i(mbist_ctrl_i),
`endif
	.CK(clk),
	.ADR(addr),
	.DI(di),
	.WEN(~we),
	.CEN(~ce),
	.OEN(~oe),
	.DOUT(doq)
);


`elsif MINSOC_XILINX_RAMB4
//
// Instantiation of FPGA memory:
//
// SPARTAN2/VIRTEX
//

wire	[dw-1:0]	doq_internal;	// output data bus

//
// Block 0
//
RAMB4_S2 ramb4_s2_0(
	.CLK(clk),
	.RST(rst),
	.ADDR(addr),
	.DI(di[1:0]),
	.EN(ce),
	.WE(we),
	.DO(doq_internal[1:0])
);

//
// Block 1
//
RAMB4_S2 ramb4_s2_1(
	.CLK(clk),
	.RST(rst),
	.ADDR(addr),
	.DI(di[3:2]),
	.EN(ce),
	.WE(we),
	.DO(doq_internal[3:2])
);

//
// Block 2
//
RAMB4_S2 ramb4_s2_2(
	.CLK(clk),
	.RST(rst),
	.ADDR(addr),
	.DI(di[5:4]),
	.EN(ce),
	.WE(we),
	.DO(doq_internal[5:4])
);

//
// Block 3
//
RAMB4_S2 ramb4_s2_3(
	.CLK(clk),
	.RST(rst),
	.ADDR(addr),
	.DI(di[7:6]),
	.EN(ce),
	.WE(we),
	.DO(doq_internal[7:6])
);

assign doq = (oe) ? (doq_internal) : { dw{1'bZ} };


`elsif MINSOC_XILINX_RAMB16
//
// Instantiation of FPGA memory:
//
// SPARTAN3/SPARTAN3E/VIRTEX2
// SPARTAN3A/VIRTEX4/VIRTEX5 are automatically reallocated by ISE
//
// Added By Nir Mor
//

wire	[dw-1:0]	doq_internal;	// output data bus

RAMB16_S9 ramb16_s9(
	.CLK(clk),
	.SSR(rst),
	.ADDR(addr),
	.DI(di),
	.DIP(1'b0),
	.EN(ce),
	.WE(we),
	.DO(doq_internal),
	.DOP()
);

assign doq = (oe) ? (doq_internal) : { dw{1'bZ} };


`elsif ALTERA_FPGA
//
// Instantiation of FPGA memory:
//
// Altera LPM
//
// Added By Jamil Khatib
//

wire    wr;

assign  wr = ce & we;

wire	[dw-1:0]	doq_internal;	// output data bus

initial $display("Using Altera LPM.");

lpm_ram_dq lpm_ram_dq_component (
        .address(addr),
        .inclock(clk),
        .data(di),
        .we(wr),
        .q(doq_internal)
);

assign doq = (oe) ? (doq_internal) : { dw{1'bZ} };

defparam lpm_ram_dq_component.lpm_width = dw,
        lpm_ram_dq_component.lpm_widthad = aw,
        lpm_ram_dq_component.lpm_indata = "REGISTERED",
        lpm_ram_dq_component.lpm_address_control = "REGISTERED",
        lpm_ram_dq_component.lpm_outdata = "UNREGISTERED",
        lpm_ram_dq_component.lpm_hint = "USE_EAB=ON";
        // examplar attribute lpm_ram_dq_component NOOPT TRUE


`endif  // !ALTERA_FPGA/MINCON_XILINX_RAMB16/MINCON_XILINX_RAMB4/VIRTUALSILICON_SSP/VIRAGE_SSP/AVANT_ATP/ARTISAN_SSP/GENERIC_MEMORY


endmodule
